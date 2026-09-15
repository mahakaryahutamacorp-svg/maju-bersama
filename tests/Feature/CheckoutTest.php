<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Category;
use App\Models\ChartOfAccount;
use App\Models\Inventory;
use App\Models\JournalHeader;
use App\Models\JournalLine;
use App\Models\Product;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CheckoutTest extends TestCase
{
    public function test_checkout_records_sale_items_and_decrements_stock_with_balanced_journal(): void
    {
        [$user, $product] = $this->checkoutSetup();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/checkout', [
            'items' => [
                ['product_id' => $product->id, 'quantity' => 2],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('status', 'success')
            ->assertJsonStructure([
                'receipt_number',
                'sale' => ['id', 'total_amount', 'items'],
            ])
            ->assertJsonPath('sale.total_amount', 22000000)
            ->assertJsonCount(1, 'sale.items');

        $saleId = $response->json('sale.id');

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'stock' => 3,
        ]);

        $this->assertDatabaseHas('sales', [
            'id' => $saleId,
            'branch_id' => $user->branch_id,
            'status' => 'completed',
            'total_amount' => 22000000,
        ]);

        $this->assertDatabaseHas('sale_items', [
            'sale_id' => $saleId,
            'product_id' => $product->id,
            'quantity' => 2,
            'price' => 11000000,
            'subtotal' => 22000000,
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

        // Cost side of the entry: 2 units at a purchase price of 100000 each.
        $this->assertDatabaseHas('journal_lines', [
            'chart_of_account_id' => ChartOfAccount::where('code', '5100')->value('id'),
            'debit' => 200000,
            'credit' => 0,
        ]);

        $this->assertDatabaseHas('journal_lines', [
            'chart_of_account_id' => ChartOfAccount::where('code', '1210')->value('id'),
            'debit' => 0,
            'credit' => 200000,
        ]);

        $journalId = JournalHeader::where('reference_number', $response->json('receipt_number'))
            ->value('id');

        $this->assertSame(4, JournalLine::where('journal_header_id', $journalId)->count());

        $this->assertSame(
            (float) JournalLine::where('journal_header_id', $journalId)->sum('debit'),
            (float) JournalLine::where('journal_header_id', $journalId)->sum('credit'),
        );
    }

    public function test_checkout_decrements_the_inventory_table(): void
    {
        [$user, $product] = $this->checkoutSetup();
        Sanctum::actingAs($user);

        $this->postJson('/api/checkout', [
            'items' => [
                ['product_id' => $product->id, 'quantity' => 2],
            ],
        ])->assertCreated();

        $this->assertDatabaseHas('inventories', [
            'branch_id' => $user->branch_id,
            'product_id' => $product->id,
            'quantity' => 3,
        ]);

        $this->assertSame(1, Inventory::withoutGlobalScopes()
            ->where('branch_id', $user->branch_id)
            ->where('product_id', $product->id)
            ->count());
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
        $this->assertDatabaseCount('sales', 0);
    }

    private function checkoutSetup(): array
    {
        $branch = Branch::create(['code' => 'PUSAT', 'name' => 'Pusat']);
        $user = User::factory()->create(['branch_id' => $branch->id]);
        $category = Category::create(['name' => 'Elektronik']);
        ChartOfAccount::insert([
            ['code' => '1110', 'name' => 'Kas', 'type' => 'asset'],
            ['code' => '1210', 'name' => 'Persediaan', 'type' => 'asset'],
            ['code' => '4110', 'name' => 'Pendapatan', 'type' => 'revenue'],
            ['code' => '5100', 'name' => 'Harga Pokok Penjualan', 'type' => 'expense'],
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