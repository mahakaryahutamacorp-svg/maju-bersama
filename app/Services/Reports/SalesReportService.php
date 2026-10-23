<?php

namespace App\Services\Reports;

use App\Models\Sale;
use App\Models\SaleItem;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class SalesReportService
{
    /**
     * Generate sales summary and detailed transactions.
     *
     * @return array{
     *     sales: Collection,
     *     total_sales: float,
     *     total_transactions: int,
     *     total_items_sold: int,
     *     average_per_transaction: float,
     *     cash_sales: float,
     *     non_cash_sales: float,
     *     tax_scope: string,
     *     dpp_total: float,
     *     ppn_total: float
     * }
     */
    public function getSales(string $startDate, string $endDate, ?int $branchId = null, string $taxScope = 'all'): array
    {
        $start = Carbon::parse($startDate)->startOfDay();
        $end = Carbon::parse($endDate)->endOfDay();
        $taxScope = in_array($taxScope, ['taxable', 'exempt'], true) ? $taxScope : 'all';

        $query = Sale::query()
            ->with(['branch', 'user', 'customer', 'items.product'])
            ->whereBetween('created_at', [$start, $end])
            ->latest('created_at');

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        $sales = $query->get()->filter(function (Sale $sale) use ($taxScope) {
            return match ($taxScope) {
                'taxable' => bccomp($this->taxableAmount($sale), '0.00', 2) === 1,
                'exempt' => bccomp($this->money($sale->subtotal_non_tax), '0.00', 2) === 1,
                default => true,
            };
        })->values();

        $amounts = $sales->mapWithKeys(fn (Sale $sale) => [$sale->id => (float) $this->scopedAmount($sale, $taxScope)]);

        $totalSales = (float) $amounts->sum();
        $totalTransactions = $sales->count();
        $totalItemsSold = (int) $sales->sum(function (Sale $sale) use ($taxScope) {
            return $sale->items->sum(function ($item) use ($taxScope) {
                if ($taxScope === 'taxable') {
                    return $item->is_taxable ? (int) $item->quantity : 0;
                }
                if ($taxScope === 'exempt') {
                    return $item->is_taxable ? 0 : (int) $item->quantity;
                }

                return (int) $item->quantity;
            });
        });

        $averagePerTransaction = $totalTransactions > 0 ? $totalSales / $totalTransactions : 0.0;

        $cashSales = (float) $sales
            ->filter(fn (Sale $sale) => strtolower($sale->payment_method ?? '') === 'cash')
            ->sum(fn (Sale $sale) => $amounts[$sale->id] ?? 0);
        $nonCashSales = $totalSales - $cashSales;

        $dppTotal = (float) $sales->sum(fn (Sale $sale) => (float) $sale->dpp_taxable);
        $ppnTotal = (float) $sales->sum(fn (Sale $sale) => (float) $sale->tax_total);

        return [
            'sales' => $sales,
            'amounts' => $amounts,
            'total_sales' => $totalSales,
            'total_transactions' => $totalTransactions,
            'total_items_sold' => $totalItemsSold,
            'average_per_transaction' => $averagePerTransaction,
            'cash_sales' => $cashSales,
            'non_cash_sales' => $nonCashSales,
            'tax_scope' => $taxScope,
            'dpp_total' => $taxScope === 'taxable' ? $dppTotal : 0.0,
            'ppn_total' => $taxScope === 'taxable' ? $ppnTotal : 0.0,
        ];
    }

    /**
     * Baris kena pajak untuk rekap internal: invoice, pelanggan, DPP, dan PPN.
     *
     * @return list<array{0: string, 1: string, 2: string, 3: string, 4: string, 5: string}>
     */
    public function taxableExportRows(string $startDate, string $endDate, ?int $branchId = null): array
    {
        $start = Carbon::parse($startDate)->startOfDay();
        $end = Carbon::parse($endDate)->endOfDay();

        $items = SaleItem::query()
            ->with(['sale.customer', 'product'])
            ->where('is_taxable', true)
            ->whereHas('sale', function ($query) use ($start, $end, $branchId) {
                $query->whereBetween('created_at', [$start, $end])
                    ->when($branchId, fn ($sale) => $sale->where('branch_id', $branchId));
            })
            ->get()
            ->sortBy(fn (SaleItem $item) => $item->sale?->created_at?->timestamp ?? 0);

        $rows = [];
        foreach ($items as $item) {
            $sale = $item->sale;
            $rows[] = [
                $sale?->created_at?->format('Y-m-d H:i') ?? '',
                (string) ($sale?->receipt_number ?? ''),
                $sale?->customer?->name ?: 'Umum',
                (string) ($item->product?->name ?? ''),
                $this->money($item->dpp_amount),
                $this->money($item->tax_amount),
            ];
        }

        return $rows;
    }

    /**
     * Baris cetak mengikuti filter pajak. Item di luar filter tidak ikut.
     *
     * @return array{
     *     scope: string,
     *     rows: list<array{date: string, receipt: string, item: string, non_tax: string, dpp: string, tax: string, total: string}>,
     *     non_tax_total: string,
     *     dpp_total: string,
     *     ppn_total: string,
     *     grand_total: string
     * }
     */
    public function printLines(string $startDate, string $endDate, ?int $branchId = null, string $taxScope = 'all'): array
    {
        $taxScope = in_array($taxScope, ['taxable', 'exempt'], true) ? $taxScope : 'all';
        $sales = $this->getSales($startDate, $endDate, $branchId, $taxScope)['sales'];
        $rows = [];
        $nonTax = '0.00';
        $dpp = '0.00';
        $ppn = '0.00';

        foreach ($sales as $sale) {
            foreach ($sale->items as $item) {
                $taxable = (bool) $item->is_taxable;
                if ($taxScope === 'taxable' && ! $taxable) {
                    continue;
                }
                if ($taxScope === 'exempt' && $taxable) {
                    continue;
                }

                $lineDpp = $taxable ? $this->money($item->dpp_amount) : '0.00';
                $lineTax = $taxable ? $this->money($item->tax_amount) : '0.00';
                $lineNonTax = $taxable ? '0.00' : $this->money($item->subtotal);
                $lineTotal = $taxable
                    ? bcadd($lineDpp, $lineTax, 2)
                    : $lineNonTax;

                $nonTax = bcadd($nonTax, $lineNonTax, 2);
                $dpp = bcadd($dpp, $lineDpp, 2);
                $ppn = bcadd($ppn, $lineTax, 2);

                $rows[] = [
                    'date' => $sale->created_at?->format('d/m/Y H:i') ?? '',
                    'receipt' => (string) $sale->receipt_number,
                    'item' => (string) ($item->product?->name ?? 'Produk'),
                    'non_tax' => $lineNonTax,
                    'dpp' => $lineDpp,
                    'tax' => $lineTax,
                    'total' => $lineTotal,
                ];
            }
        }

        return [
            'scope' => $taxScope,
            'rows' => $rows,
            'non_tax_total' => $nonTax,
            'dpp_total' => $dpp,
            'ppn_total' => $ppn,
            'grand_total' => bcadd(bcadd($nonTax, $dpp, 2), $ppn, 2),
        ];
    }

    private function scopedAmount(Sale $sale, string $taxScope): string
    {
        return match ($taxScope) {
            'taxable' => $this->taxableAmount($sale),
            'exempt' => $this->money($sale->subtotal_non_tax),
            default => $this->money($sale->total_amount),
        };
    }

    private function taxableAmount(Sale $sale): string
    {
        return bcadd($this->money($sale->dpp_taxable), $this->money($sale->tax_total), 2);
    }

    private function money(mixed $amount): string
    {
        return bcadd((string) ($amount ?? '0'), '0', 2);
    }
}
