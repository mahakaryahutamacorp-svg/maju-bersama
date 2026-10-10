<?php

namespace Tests\Feature\Api;

use App\Models\ChartOfAccount;
use App\Models\JournalHeader;
use App\Models\JournalLine;
use App\Models\Supplier;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Api\Concerns\AssertsMoney;
use Tests\Feature\Api\Concerns\PreparesLedger;
use Tests\TestCase;

class GoodsReceiptApiTest extends TestCase
{
    use AssertsMoney;
    use PreparesLedger;

    public function test_tc005_purchase_order_does_not_move_stock_and_receipt_posts_a_balanced_journal(): void
    {
        [$branch, $master] = $this->branchWithRoles();
        $this->seedRetailAccounts();
        $product = $this->sellableProduct($branch, 5, '110000.00', '25000.00');
        $supplier = Supplier::create([
            'branch_id' => $branch->id,
            'name' => 'Pemasok Uji',
            'is_active' => true,
        ]);
        Sanctum::actingAs($master);

        $order = $this->postJson('/api/purchase-orders', [
            'supplier_id' => $supplier->id,
            'branch_id' => $branch->id,
            'order_date' => '2026-10-10',
            'items' => [
                ['product_id' => $product->id, 'quantity' => 4, 'unit_price' => '25000.00'],
            ],
        ]);

        $order->assertCreated();
        $this->assertSame(5, (int) $product->fresh()->stock);
        $this->assertSame(0, JournalHeader::count());

        $receipt = $this->postJson('/api/goods-receipts', [
            'purchase_order_id' => $order->json('data.id'),
            'date' => '2026-10-10',
            'items' => [
                ['product_id' => $product->id, 'quantity' => 4, 'unit_price' => '25000.00'],
            ],
        ]);

        $receipt->assertCreated();
        $this->assertSame(9, (int) $product->fresh()->stock);

        $journal = JournalHeader::where('reference_number', $receipt->json('data.reference_number'))->firstOrFail();
        $lines = JournalLine::where('journal_header_id', $journal->id)->get();

        $this->assertMoneySame($lines->sum('debit'), $lines->sum('credit'));
        $this->assertMoneySame($this->amount($lines, '1210', 'debit'), '100000.00');
        $this->assertMoneySame($this->amount($lines, '2110', 'credit'), '100000.00');
        $this->assertMoneyNotHundredfold($this->amount($lines, '1210', 'debit'), '100000.00');
    }

    /**
     * @param  \Illuminate\Support\Collection<int, JournalLine>  $lines
     */
    private function amount($lines, string $code, string $side): string
    {
        $accountId = ChartOfAccount::where('code', $code)->value('id');

        return $this->money($lines->where('chart_of_account_id', $accountId)->sum($side));
    }
}
