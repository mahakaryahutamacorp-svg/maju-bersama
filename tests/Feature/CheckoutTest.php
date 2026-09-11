<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Category;
use App\Models\ChartOfAccount;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CheckoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkout_decrements_stock_and_creates_balanced_journal(): void
    {
        [$user, $product] = $this->checkoutSetup();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/checkout', [
            'items' => [
                ['product_id' => $product->id, 'quantity' => 2],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('total', 220000)
            ->assertJsonCount(2, 'journal.journal_lines');

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'stock' => 3,
        ]);
        $this->assertDatabaseHas('journal_headers', [
            'description' => 'POS Sale',
            'branch_id' => $user->branch_id,
        ]);
        $this->assertDatabaseHas('journal_lines', [
            'chart_of_account_id' => ChartOfAccount::where('code', '1110')->value('id'),
            'debit' => 220000,
            'credit' => 0,
        ]);
        $this->assertDatabaseHas('journal_lines', [
            'chart_of_account_id' => ChartOfAccount::where('code', '4110')->value('id'),
            'debit' => 0,
            'credit' => 220000,
        ]);
    }

    public function test_checkout_rejects_insufficient_stock_without_changes(): void
    {
        [$user, $product] = $this->checkoutSetup();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/checkout', [
            'items' => [
                ['product_id' => $product->id, 'quantity' => 6],
            ],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('items');
        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock' => 5]);
        $this->assertDatabaseCount('journal_headers', 0);
    }

    private function checkoutSetup(): array
    {
        $branch = Branch::create(['code' => 'PUSAT', 'name' => 'Pusat']);
        $user = User::factory()->create(['branch_id' => $branch->id]);
        $category = Category::create(['name' => 'Elektronik']);
        ChartOfAccount::insert([
            ['code' => '1110', 'name' => 'Kas', 'type' => 'asset'],
            ['code' => '4110', 'name' => 'Pendapatan', 'type' => 'revenue'],
        ]);
        $product = Product::create([
            'branch_id' => $branch->id,
            'category_id' => $category->id,
            'sku' => 'ELK-001',
            'name' => 'Test Product',
            'purchase_price' => 100000,
            'selling_price' => 110000,
            'stock' => 5,
        ]);

        return [$user, $product];
    }
}