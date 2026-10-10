<?php

namespace Tests\Feature\Api;

use App\Models\ChartOfAccount;
use App\Models\JournalHeader;
use App\Models\JournalLine;
use App\Models\Sale;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Api\Concerns\AssertsMoney;
use Tests\Feature\Api\Concerns\PreparesLedger;
use Tests\TestCase;

class CheckoutApiTest extends TestCase
{
    use AssertsMoney;
    use PreparesLedger;

    public function test_tc003_checkout_uses_master_price_cuts_stock_and_balances_the_journal(): void
    {
        [$branch, $master] = $this->branchWithRoles();
        $this->seedRetailAccounts();
        $product = $this->sellableProduct($branch, 5, '110000.00', '100000.00');
        Sanctum::actingAs($master);

        $response = $this->postJson('/api/checkout', [
            'items' => [
                ['product_id' => $product->id, 'quantity' => 2, 'price' => 1],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('sale.items.0.price', 110000)
            ->assertJsonPath('sale.items.0.quantity', 2);

        $this->assertSame(3, (int) $product->fresh()->stock);
        $this->assertDatabaseHas('inventories', [
            'branch_id' => $branch->id,
            'product_id' => $product->id,
            'quantity' => 3,
        ]);

        $journal = JournalHeader::where('reference_number', $response->json('receipt_number'))->firstOrFail();
        $lines = JournalLine::where('journal_header_id', $journal->id)->get();

        $this->assertMoneySame($lines->sum('debit'), $lines->sum('credit'));
        $this->assertMoneySame($this->lineAmount($lines, '1110', 'debit'), '220000.00');
        $this->assertMoneySame($this->lineAmount($lines, '4110', 'credit'), '220000.00');
        $this->assertMoneySame($this->lineAmount($lines, '5100', 'debit'), '200000.00');
        $this->assertMoneySame($this->lineAmount($lines, '1210', 'credit'), '200000.00');
        $this->assertMoneyNotHundredfold($this->lineAmount($lines, '1110', 'debit'), '220000.00');
    }

    public function test_tc004_checkout_above_stock_rolls_back_stock_sale_and_journal(): void
    {
        [$branch, $master] = $this->branchWithRoles();
        $this->seedRetailAccounts();
        $product = $this->sellableProduct($branch, 5);
        Sanctum::actingAs($master);

        $this->postJson('/api/checkout', [
            'items' => [
                ['product_id' => $product->id, 'quantity' => 6],
            ],
        ])->assertStatus(422)
            ->assertJsonValidationErrors('items');

        $this->assertSame(5, (int) $product->fresh()->stock);
        $this->assertSame(0, Sale::count());
        $this->assertSame(0, JournalHeader::count());
    }

    public function test_tc006_single_discount_is_bounded_and_keeps_the_journal_balanced(): void
    {
        [$branch, $master] = $this->branchWithRoles();
        $this->seedRetailAccounts();
        $product = $this->sellableProduct($branch, 5, '110000.00', '100000.00');
        Sanctum::actingAs($master);

        $this->postJson('/api/checkout', [
            'items' => [
                ['product_id' => $product->id, 'quantity' => 2],
            ],
            'discount_amount' => -1,
        ])->assertStatus(422)
            ->assertJsonValidationErrors('discount_amount');

        $this->postJson('/api/checkout', [
            'items' => [
                ['product_id' => $product->id, 'quantity' => 2],
            ],
            'discount_amount' => '220001.00',
        ])->assertStatus(422)
            ->assertJsonValidationErrors('discount_amount');

        $this->assertSame(0, Sale::count());
        $this->assertSame(5, (int) $product->fresh()->stock);

        $response = $this->postJson('/api/checkout', [
            'items' => [
                ['product_id' => $product->id, 'quantity' => 2],
            ],
            'discount_amount' => '10000.00',
        ])->assertCreated();

        $journal = JournalHeader::where('reference_number', $response->json('receipt_number'))->firstOrFail();
        $lines = JournalLine::where('journal_header_id', $journal->id)->get();

        $this->assertMoneySame($lines->sum('debit'), $lines->sum('credit'));
        $this->assertMoneySame($this->lineAmount($lines, '1110', 'debit'), '210000.00');
        $this->assertMoneySame($this->lineAmount($lines, '4130', 'debit'), '10000.00');
        $this->assertMoneySame($this->lineAmount($lines, '4110', 'credit'), '220000.00');
        $this->assertSame(3, (int) $product->fresh()->stock);

        // TODO TC006/TC012: diskon bertingkat belum dimodelkan. Yang ada hanya satu discount_amount.
    }

    /**
     * @param  \Illuminate\Support\Collection<int, JournalLine>  $lines
     */
    private function lineAmount($lines, string $code, string $side): string
    {
        $accountId = ChartOfAccount::where('code', $code)->value('id');
        $amount = $lines->where('chart_of_account_id', $accountId)->sum($side);

        return $this->money($amount);
    }
}
