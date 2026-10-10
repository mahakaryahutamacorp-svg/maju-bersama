<?php

namespace Tests\Feature\Api;

use App\Models\ChartOfAccount;
use App\Models\JournalLine;
use App\Models\Sale;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Api\Concerns\AssertsMoney;
use Tests\Feature\Api\Concerns\PreparesLedger;
use Tests\TestCase;

class ItemDiscountCheckoutTest extends TestCase
{
    use AssertsMoney;
    use PreparesLedger;

    public function test_product_discount_lowers_line_and_total_and_is_debited_to_sales_discount(): void
    {
        [$branch, $master] = $this->branchWithRoles();
        $this->seedRetailAccounts();
        $discounted = $this->sellableProduct($branch, 5, '10000.00', '6000.00');
        $regular = $this->sellableProduct($branch, 5, '5000.00', '3000.00');
        Sanctum::actingAs($master);

        $response = $this->postJson('/api/checkout', [
            'discount_amount' => 1000,
            'items' => [
                ['product_id' => $discounted->id, 'quantity' => 2, 'discount_amount' => 2000],
                ['product_id' => $regular->id, 'quantity' => 1],
            ],
        ])->assertCreated()
            ->assertJsonPath('sale.total_amount', '22000.00')
            ->assertJsonPath('sale.discount_amount', '1000.00');

        $sale = Sale::withoutGlobalScopes()->with('items')->findOrFail($response->json('sale.id'));
        $discountedLine = $sale->items->firstWhere('product_id', $discounted->id);
        $regularLine = $sale->items->firstWhere('product_id', $regular->id);

        $this->assertSame('10000.00', $discountedLine->price);
        $this->assertSame('2000.00', $discountedLine->discount_amount);
        $this->assertSame('18000.00', $discountedLine->subtotal);
        $this->assertSame('0.00', $regularLine->discount_amount);
        $this->assertSame('5000.00', $regularLine->subtotal);

        $lines = JournalLine::query()
            ->whereHas('journalHeader', fn ($query) => $query->where('reference_number', $sale->receipt_number))
            ->get();
        $byCode = fn (string $code) => $lines->where('chart_of_account_id', ChartOfAccount::where('code', $code)->value('id'));

        $this->assertMoneySame($byCode('1110')->sum('debit'), '22000.00');
        $this->assertMoneySame($byCode('4130')->sum('debit'), '3000.00');
        $this->assertMoneySame($byCode('4110')->sum('credit'), '25000.00');
        $this->assertMoneySame($byCode('5100')->sum('debit'), '15000.00');
        $this->assertMoneySame($lines->sum('debit'), $lines->sum('credit'));
    }

    public function test_split_lines_of_the_same_product_add_their_discounts(): void
    {
        [$branch, $master] = $this->branchWithRoles();
        $this->seedRetailAccounts();
        $product = $this->sellableProduct($branch, 5, '33.33', '0.00');
        Sanctum::actingAs($master);

        $this->postJson('/api/checkout', [
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1, 'discount_amount' => '0.33'],
                ['product_id' => $product->id, 'quantity' => 2, 'discount_amount' => '0.66'],
            ],
        ])->assertCreated()
            ->assertJsonPath('sale.total_amount', '99.00')
            ->assertJsonPath('sale.items.0.discount_amount', '0.99')
            ->assertJsonPath('sale.items.0.subtotal', '99.00');
    }

    public function test_product_discount_above_its_line_is_rejected_without_touching_stock(): void
    {
        [$branch, $master] = $this->branchWithRoles();
        $this->seedRetailAccounts();
        $product = $this->sellableProduct($branch, 5, '10000.00', '6000.00');
        Sanctum::actingAs($master);

        $this->postJson('/api/checkout', [
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1, 'discount_amount' => '10000.01'],
            ],
        ])->assertStatus(422)
            ->assertJsonValidationErrors('items');

        $this->assertSame(5, (int) $product->fresh()->stock);
        $this->assertSame(0, Sale::withoutGlobalScopes()->count());
        $this->assertSame(0, JournalLine::query()->count());
    }

    public function test_negative_product_discount_is_rejected(): void
    {
        [$branch, $master] = $this->branchWithRoles();
        $this->seedRetailAccounts();
        $product = $this->sellableProduct($branch, 5, '10000.00', '6000.00');
        Sanctum::actingAs($master);

        $this->postJson('/api/checkout', [
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1, 'discount_amount' => -500],
            ],
        ])->assertStatus(422)
            ->assertJsonValidationErrors('items.0.discount_amount');
    }

    public function test_transaction_discount_is_capped_by_subtotal_after_product_discounts(): void
    {
        [$branch, $master] = $this->branchWithRoles();
        $this->seedRetailAccounts();
        $product = $this->sellableProduct($branch, 5, '10000.00', '6000.00');
        Sanctum::actingAs($master);

        $this->postJson('/api/checkout', [
            'discount_amount' => 8001,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1, 'discount_amount' => 2000],
            ],
        ])->assertStatus(422)
            ->assertJsonValidationErrors('discount_amount');

        $this->assertSame(5, (int) $product->fresh()->stock);
    }

    public function test_pos_screen_route_accepts_product_discount_and_prints_it_on_the_receipt(): void
    {
        [$branch, , , $cashier] = $this->branchWithRoles();
        $this->seedRetailAccounts();
        $product = $this->sellableProduct($branch, 5, '10000.00', '6000.00');

        $response = $this->actingAs($cashier)->postJson('/pos', [
            'items' => [
                ['product_id' => $product->id, 'quantity' => 3, 'discount_amount' => '1500.50'],
            ],
        ])->assertCreated()
            ->assertJsonPath('sale.total_amount', '28499.50')
            ->assertJsonPath('sale.items.0.discount_amount', '1500.50');

        $this->actingAs($cashier)
            ->get(route('pos.receipt', $response->json('receipt_number')))
            ->assertOk()
            ->assertSeeInOrder(['3 x 10.000', '30.000', 'Diskon', '-1.500,50', 'DISKON PRODUK:', 'Rp 28.499,50'], false);
    }
}
