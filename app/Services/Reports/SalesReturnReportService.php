<?php

namespace App\Services\Reports;

use App\Models\SalesReturn;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class SalesReturnReportService
{
    /**
     * @return array{returns: Collection, total_amount: float, total_cost: float, total_documents: int, total_quantity: int}
     */
    public function getReturns(string $startDate, string $endDate, ?int $branchId = null): array
    {
        $start = Carbon::parse($startDate)->toDateString();
        $end = Carbon::parse($endDate)->toDateString();

        $returns = SalesReturn::withoutGlobalScopes()
            ->with([
                'branch',
                'user',
                'sale',
                'items.product' => fn ($query) => $query->withoutGlobalScopes()->withTrashed(),
            ])
            ->whereDate('return_date', '>=', $start)
            ->whereDate('return_date', '<=', $end)
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->orderByDesc('return_date')
            ->orderByDesc('id')
            ->get();

        return [
            'returns' => $returns,
            'total_amount' => round((float) $returns->sum('total_amount'), 2),
            'total_cost' => round((float) $returns->sum('total_cost'), 2),
            'total_documents' => $returns->count(),
            'total_quantity' => (int) $returns->sum(fn (SalesReturn $row) => $row->items->sum('quantity')),
        ];
    }
}
