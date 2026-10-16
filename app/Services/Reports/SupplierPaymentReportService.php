<?php

namespace App\Services\Reports;

use App\Models\Payment;
use App\Models\SupplierPayment;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class SupplierPaymentReportService
{
    /**
     * @return array{payments: Collection, total_amount: float, total_documents: int}
     */
    public function getPayments(string $startDate, string $endDate, ?int $branchId = null): array
    {
        $start = Carbon::parse($startDate)->toDateString();
        $end = Carbon::parse($endDate)->toDateString();

        $current = Payment::withoutGlobalScopes()
            ->with([
                'branch',
                'supplier',
                'account',
                'allocations.purchaseOrder',
                'allocations.goodsReceipt',
            ])
            ->where('type', 'AP')
            ->whereDate('payment_date', '>=', $start)
            ->whereDate('payment_date', '<=', $end)
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->get()
            ->map(function (Payment $payment) {
                $references = $payment->allocations
                    ->map(function ($allocation) {
                        return $allocation->purchaseOrder?->reference_number
                            ?? $allocation->goodsReceipt?->reference_number;
                    })
                    ->filter()
                    ->unique()
                    ->values()
                    ->all();

                return [
                    'date' => $payment->payment_date?->toDateString(),
                    'reference' => $payment->reference_number,
                    'supplier_name' => $payment->supplier?->name ?? 'Supplier',
                    'branch_name' => $payment->branch?->name ?? '-',
                    'method' => $payment->account?->name ?? 'Kas / Bank',
                    'amount' => round((float) $payment->amount, 2),
                    'notes' => $payment->notes,
                    'allocations' => $references,
                    'source' => 'Buku hutang',
                ];
            });

        $legacy = SupplierPayment::withoutGlobalScopes()
            ->with(['branch', 'supplier', 'chartOfAccount'])
            ->whereDate('payment_date', '>=', $start)
            ->whereDate('payment_date', '<=', $end)
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->get()
            ->map(fn (SupplierPayment $payment) => [
                'date' => $payment->payment_date?->toDateString(),
                'reference' => $payment->reference_number ?: ('SP-'.$payment->id),
                'supplier_name' => $payment->supplier?->name ?? 'Supplier',
                'branch_name' => $payment->branch?->name ?? '-',
                'method' => $payment->chartOfAccount?->name ?? $payment->payment_method,
                'amount' => round((float) $payment->amount, 2),
                'notes' => $payment->notes,
                'allocations' => [],
                'source' => 'Catatan lama',
            ]);

        $payments = $current->concat($legacy)
            ->sortByDesc(fn (array $row) => ($row['date'] ?? '').'-'.$row['reference'])
            ->values();

        return [
            'payments' => $payments,
            'total_amount' => round((float) $payments->sum('amount'), 2),
            'total_documents' => $payments->count(),
        ];
    }
}
