<?php

namespace App\Services;

use App\Models\GoodsReceipt;
use App\Models\Payment;
use App\Models\PurchaseOrder;
use App\Models\PurchaseReturn;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Buku Pemasok: menyusun nota belanja, pembayaran, dan retur satu supplier
 * dalam bahasa toko, beserta sisa hutang per nota (urut FIFO).
 */
class SupplierLedgerService
{
    /**
     * @return array{
     *     invoices: Collection,
     *     open_invoices: Collection,
     *     total_debt: string,
     *     total_purchased: string,
     *     total_paid: string,
     *     total_returned: string,
     *     payments: Collection,
     *     returns: Collection
     * }
     */
    public function build(Supplier $supplier): array
    {
        $invoices = $this->computeInvoices(
            $this->ordersQuery($supplier)->get(),
            $this->directReceiptsQuery($supplier)->get(),
            $this->unallocatedCredit($supplier)
        );

        $payments = $this->paymentRows($supplier);
        $returns = $this->returnRows($supplier);

        return [
            'invoices' => $invoices->sortByDesc('sort_key')->values(),
            'open_invoices' => $invoices->filter(fn (array $row) => bccomp($row['outstanding'], '0.00', 2) > 0)->values(),
            'total_debt' => $this->sum($invoices, 'outstanding'),
            'total_purchased' => $this->sum($invoices, 'billable'),
            'total_paid' => $this->sum($payments, 'amount'),
            'total_returned' => $this->sum($returns, 'amount'),
            'payments' => $payments,
            'returns' => $returns,
        ];
    }

    /**
     * Nota (pesanan barang) milik supplier, urut dari yang paling lama.
     */
    public function ordersQuery(Supplier $supplier): Builder
    {
        return PurchaseOrder::query()
            ->where('supplier_id', $supplier->id)
            ->where('status', '!=', 'cancelled')
            ->with(['items.product', 'goodsReceipts.items.product', 'goodsReceipts.purchaseReturns'])
            ->orderBy('order_date')
            ->orderBy('id');
    }

    /**
     * Penerimaan barang langsung (tanpa pesanan) yang terikat ke supplier, tunai maupun tempo.
     */
    public function directReceiptsQuery(Supplier $supplier): Builder
    {
        return GoodsReceipt::query()
            ->where('supplier_id', $supplier->id)
            ->whereNull('purchase_order_id')
            ->with(['items.product', 'purchaseReturns'])
            ->orderBy('date')
            ->orderBy('id');
    }

    /**
     * Kredit yang mengurangi hutang tetapi tidak terikat ke nota tertentu:
     * pembayaran modul lama dan retur tanpa referensi penerimaan barang.
     */
    public function unallocatedCredit(Supplier $supplier): string
    {
        $legacyPayments = (string) SupplierPayment::query()
            ->where('supplier_id', $supplier->id)
            ->sum('amount');

        $looseReturns = (string) PurchaseReturn::query()
            ->where('supplier_id', $supplier->id)
            ->whereNull('goods_receipt_id')
            ->sum('total_amount');

        return bcadd($this->money($legacyPayments), $this->money($looseReturns), 2);
    }

    /**
     * Hitung sisa hutang per nota (pesanan barang dan penerimaan langsung), urut dari yang paling lama.
     * Kredit lepas dipotongkan ke nota terlama lebih dulu.
     *
     * @param  Collection<int, PurchaseOrder>  $orders
     * @param  Collection<int, GoodsReceipt>  $directReceipts
     */
    public function computeInvoices(Collection $orders, Collection $directReceipts, string $unallocatedCredit): Collection
    {
        $rows = $orders->map(fn (PurchaseOrder $order) => $this->orderRow($order))
            ->concat($directReceipts->map(fn (GoodsReceipt $receipt) => $this->receiptRow($receipt)))
            ->sortBy('sort_key')
            ->values();

        $credit = $this->money($unallocatedCredit);

        return $rows->map(function (array $row) use (&$credit): array {
            $outstanding = $row['outstanding'];

            if (bccomp($credit, '0.00', 2) > 0 && bccomp($outstanding, '0.00', 2) > 0) {
                $applied = bccomp($credit, $outstanding, 2) >= 0 ? $outstanding : $credit;
                $outstanding = bcsub($outstanding, $applied, 2);
                $credit = bcsub($credit, $applied, 2);
            }

            $row['outstanding'] = $outstanding;
            $row['paid'] = bcsub(bcsub($row['billable'], $row['returned'], 2), $outstanding, 2);
            $row['status'] = $this->invoiceStatus($row['received'], $row['billable'], $outstanding);

            return $row;
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function orderRow(PurchaseOrder $order): array
    {
        $billable = '0.00';
        $returned = '0.00';
        foreach ($order->goodsReceipts as $receipt) {
            $billable = bcadd($billable, $this->money($receipt->total_amount), 2);
            foreach ($receipt->purchaseReturns as $return) {
                $returned = bcadd($returned, $this->money($return->total_amount), 2);
            }
        }

        $received = $order->goodsReceipts->isNotEmpty();
        $date = $order->goodsReceipts->min('date') ?? $order->order_date;

        return [
            'key' => 'po-'.$order->id,
            'type' => 'purchase_order',
            'source_id' => $order->id,
            'date' => $date,
            'due_date' => $order->due_date,
            'sort_key' => sprintf('%s-%010d', ($order->order_date?->format('Y-m-d') ?? ''), $order->id),
            'description' => $this->invoiceDescription($order, $received),
            'billable' => $billable,
            'returned' => $returned,
            'received' => $received,
            'outstanding' => $this->remaining($billable, $returned, $order->paid_amount),
        ];
    }

    /**
     * Penerimaan tunai dianggap lunas saat itu juga; hanya penerimaan tempo yang menyisakan hutang.
     *
     * @return array<string, mixed>
     */
    private function receiptRow(GoodsReceipt $receipt): array
    {
        $billable = $this->money($receipt->total_amount);
        $returned = $receipt->purchaseReturns->reduce(
            fn (string $carry, PurchaseReturn $return) => bcadd($carry, $this->money($return->total_amount), 2),
            '0.00'
        );
        $isCredit = $receipt->payment_type === 'credit';
        $paid = $isCredit ? $receipt->paid_amount : $billable;

        $names = $this->summarizeNames($receipt->items->map(fn ($item) => $item->product?->name));

        return [
            'key' => 'gr-'.$receipt->id,
            'type' => 'goods_receipt',
            'source_id' => $receipt->id,
            'date' => $receipt->date,
            'due_date' => null,
            'sort_key' => sprintf('%s-%010d', ($receipt->date?->format('Y-m-d') ?? ''), $receipt->id),
            'description' => 'Terima Barang - '.$names.($isCredit ? '' : ' (Tunai)'),
            'billable' => $billable,
            'returned' => $returned,
            'received' => true,
            'outstanding' => $this->remaining($billable, $returned, $paid),
        ];
    }

    private function remaining(string $billable, string $returned, mixed $paid): string
    {
        $outstanding = bcsub(bcsub($billable, $returned, 2), $this->money($paid), 2);

        return bccomp($outstanding, '0.00', 2) < 0 ? '0.00' : $outstanding;
    }

    /**
     * Bagi nominal pembayaran ke nota terlama lebih dulu (FIFO).
     *
     * @param  Collection<int, array{type: string, source_id: int, outstanding: string}>  $openInvoices  urut paling lama
     * @return array<int, array{purchase_order_id: int|null, goods_receipt_id: int|null, allocated_amount: string}>
     */
    public function allocateFifo(Collection $openInvoices, string $amount): array
    {
        $remaining = $this->money($amount);
        $allocations = [];

        foreach ($openInvoices as $invoice) {
            if (bccomp($remaining, '0.00', 2) <= 0) {
                break;
            }

            $portion = bccomp($remaining, $invoice['outstanding'], 2) >= 0 ? $invoice['outstanding'] : $remaining;
            if (bccomp($portion, '0.00', 2) <= 0) {
                continue;
            }

            $allocations[] = [
                'purchase_order_id' => $invoice['type'] === 'purchase_order' ? (int) $invoice['source_id'] : null,
                'goods_receipt_id' => $invoice['type'] === 'goods_receipt' ? (int) $invoice['source_id'] : null,
                'allocated_amount' => $portion,
            ];
            $remaining = bcsub($remaining, $portion, 2);
        }

        return $allocations;
    }

    public function paymentDescription(?string $accountName, ?string $notes, int $invoiceCount = 0): string
    {
        $base = $notes !== null && trim($notes) !== ''
            ? trim($notes)
            : $this->paymentMethodLabel($accountName);

        if ($invoiceCount > 1) {
            return "{$base} (memotong {$invoiceCount} nota)";
        }

        return $base;
    }

    private function paymentRows(Supplier $supplier): Collection
    {
        $payments = Payment::query()
            ->where('type', 'AP')
            ->where(function ($q) use ($supplier) {
                $q->where('supplier_id', $supplier->id)
                    ->orWhereHas('allocations.purchaseOrder', fn ($po) => $po->where('supplier_id', $supplier->id))
                    ->orWhereHas('allocations.goodsReceipt', fn ($gr) => $gr->where('supplier_id', $supplier->id));
            })
            ->with(['account', 'allocations'])
            ->get()
            ->map(fn (Payment $payment) => [
                'date' => $payment->payment_date,
                'description' => $this->paymentDescription(
                    $payment->account?->name,
                    $payment->notes,
                    $payment->allocations->count()
                ),
                'channel' => $this->paymentMethodLabel($payment->account?->name),
                'amount' => $this->money($payment->amount),
                'sort_key' => ($payment->payment_date?->format('Y-m-d') ?? '').'-p'.str_pad((string) $payment->id, 10, '0', STR_PAD_LEFT),
            ]);

        $legacy = SupplierPayment::query()
            ->where('supplier_id', $supplier->id)
            ->with('chartOfAccount')
            ->get()
            ->map(fn (SupplierPayment $payment) => [
                'date' => $payment->payment_date,
                'description' => $this->paymentDescription($payment->chartOfAccount?->name, $payment->notes),
                'channel' => $this->paymentMethodLabel($payment->chartOfAccount?->name, $payment->payment_method),
                'amount' => $this->money($payment->amount),
                'sort_key' => ($payment->payment_date?->format('Y-m-d') ?? '').'-s'.str_pad((string) $payment->id, 10, '0', STR_PAD_LEFT),
            ]);

        return $payments->concat($legacy)->sortByDesc('sort_key')->values();
    }

    private function returnRows(Supplier $supplier): Collection
    {
        return PurchaseReturn::query()
            ->where('supplier_id', $supplier->id)
            ->with('items.product')
            ->latest('return_date')
            ->latest('id')
            ->get()
            ->map(fn (PurchaseReturn $return) => [
                'date' => $return->return_date,
                'description' => $this->returnDescription($return),
                'item_count' => $return->items->sum('quantity'),
                'amount' => $this->money($return->total_amount),
            ]);
    }

    private function invoiceDescription(PurchaseOrder $order, bool $received): string
    {
        $productNames = $received
            ? $order->goodsReceipts->flatMap(fn ($receipt) => $receipt->items->map(fn ($item) => $item->product?->name))
            : $order->items->map(fn ($item) => $item->product?->name);

        $prefix = $received ? 'Terima Barang' : 'Pesan Barang';

        return $prefix.' - '.$this->summarizeNames($productNames);
    }

    private function returnDescription(PurchaseReturn $return): string
    {
        if ($return->notes !== null && trim($return->notes) !== '') {
            return 'Retur Barang - '.trim($return->notes);
        }

        return 'Retur Barang - '.$this->summarizeNames($return->items->map(fn ($item) => $item->product?->name));
    }

    private function summarizeNames(Collection $names): string
    {
        $unique = $names->filter()->unique()->values();

        if ($unique->isEmpty()) {
            return 'Barang Dagangan';
        }

        return $unique->count() > 1 ? $unique->first().' dkk' : $unique->first();
    }

    private function invoiceStatus(bool $received, string $billable, string $outstanding): string
    {
        if (! $received) {
            return 'Menunggu Barang';
        }

        if (bccomp($outstanding, '0.00', 2) <= 0) {
            return 'Lunas';
        }

        return bccomp($outstanding, $billable, 2) < 0 ? 'Dicicil' : 'Belum Dibayar';
    }

    private function paymentMethodLabel(?string $accountName, ?string $method = null): string
    {
        $name = $accountName ?? 'Kas';
        $isBank = str_contains(strtolower($name), 'bank') || in_array(strtolower((string) $method), ['transfer', 'giro'], true);

        return $isBank ? "Bayar Hutang via Transfer {$name}" : "Bayar Hutang Tunai dari {$name}";
    }

    private function sum(Collection $rows, string $key): string
    {
        return $rows->reduce(fn (string $carry, array $row) => bcadd($carry, $this->money($row[$key]), 2), '0.00');
    }

    private function money(mixed $value): string
    {
        return number_format((float) ($value ?? 0), 2, '.', '');
    }
}
