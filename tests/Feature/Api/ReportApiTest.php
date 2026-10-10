<?php

namespace Tests\Feature\Api;

use App\Models\Category;
use App\Models\Product;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Api\Concerns\AssertsMoney;
use Tests\Feature\Api\Concerns\PreparesLedger;
use Tests\TestCase;

class ReportApiTest extends TestCase
{
    use AssertsMoney;
    use PreparesLedger;

    public function test_tc008_search_text_is_bound_and_does_not_return_the_whole_catalog(): void
    {
        [$branch, $master] = $this->branchWithRoles();
        $category = Category::create(['name' => 'Katalog']);

        Product::create([
            'branch_id' => $branch->id,
            'category_id' => $category->id,
            'sku' => 'AMAN-1',
            'name' => 'Urea',
            'purchase_price' => '1000.00',
            'selling_price' => '1500.00',
            'stock' => 2,
        ]);
        Product::create([
            'branch_id' => $branch->id,
            'category_id' => $category->id,
            'sku' => 'AMAN-2',
            'name' => 'NPK',
            'purchase_price' => '1000.00',
            'selling_price' => '1500.00',
            'stock' => 2,
        ]);

        Sanctum::actingAs($master);

        $injection = $this->getJson('/api/products?search='.urlencode("%' OR 1=1 --"));
        $injection->assertOk();

        $names = collect($injection->json('data'))->pluck('name');
        $this->assertCount(0, $names);
        $this->assertFalse($names->contains('Urea'));
        $this->assertFalse($names->contains('NPK'));

        $this->getJson('/api/products?search=Urea')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Urea');
    }

    public function test_tc009_balance_sheet_stays_balanced_after_a_sale(): void
    {
        [$branch, $master] = $this->branchWithRoles();
        $this->seedRetailAccounts();
        $product = $this->sellableProduct($branch, 5, '110000.00', '100000.00');
        Sanctum::actingAs($master);

        $this->postJson('/api/checkout', [
            'items' => [
                ['product_id' => $product->id, 'quantity' => 2],
            ],
        ])->assertCreated();

        $balance = $this->getJson('/api/reports/balance-sheet')->assertOk();
        $income = $this->getJson('/api/reports/income-statement')->assertOk();

        $this->assertMoneySame($balance->json('data.difference'), '0.00');
        $this->assertTrue($balance->json('data.is_balanced'));

        $cash = collect($balance->json('data.assets.accounts'))->firstWhere('code', '1110');
        $this->assertNotNull($cash);
        $this->assertMoneySame($cash['amount'], '220000.00');
        $this->assertMoneyNotHundredfold($cash['amount'], '220000.00');

        $this->assertMoneySame($income->json('data.revenue.total'), '220000.00');
        $this->assertMoneySame($income->json('data.cogs.total'), '200000.00');
        $this->assertMoneySame($income->json('data.net_profit'), '20000.00');
        $this->assertMoneyNotHundredfold($income->json('data.revenue.total'), '220000.00');
    }
}
