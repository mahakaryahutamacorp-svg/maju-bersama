<?php

namespace App\Services\Reports;

use App\Models\FixedAsset;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class FixedAssetReportService
{
    /**
     * Get fixed assets list with posted depreciation, book value, and straight-line schedule.
     * This method only reads. It does not post depreciation journals.
     */
    public function getAssets(?int $branchId = null, ?string $asOfDate = null): Collection
    {
        $asOf = Carbon::parse($asOfDate ?? now()->toDateString())->startOfDay();

        $query = FixedAsset::withoutGlobalScopes()
            ->with([
                'branch',
                'depreciations' => fn ($depreciation) => $depreciation->whereDate('depreciation_date', '<=', $asOf->toDateString()),
            ]);

        if ($branchId !== null) {
            $query->where('branch_id', $branchId);
        }

        return $query->orderBy('id', 'asc')->get()->map(function (FixedAsset $asset) use ($asOf) {
            $acquisitionValue = (float) $asset->purchase_price;
            $salvageValue = (float) $asset->salvage_value;
            $depreciableBase = max(0, $acquisitionValue - $salvageValue);
            $usefulLife = (int) $asset->useful_life_months;
            $monthly = (float) $asset->monthly_depreciation;
            $accumulatedDepreciation = round((float) $asset->depreciations->sum('amount'), 2);
            $bookValue = round($acquisitionValue - $accumulatedDepreciation, 2);

            $purchaseDate = $asset->purchase_date ? Carbon::parse($asset->purchase_date)->startOfDay() : $asOf->copy();
            $monthsElapsed = (int) $purchaseDate->diffInMonths($asOf, false);
            if ($monthsElapsed < 0) {
                $monthsElapsed = 0;
            }
            if ($usefulLife > 0) {
                $monthsElapsed = min($monthsElapsed, $usefulLife);
            }

            $scheduled = $usefulLife > 0 ? round(min($depreciableBase, $monthly * $monthsElapsed), 2) : 0.0;

            return (object) [
                'id' => $asset->id,
                'branch_id' => $asset->branch_id,
                'branch_name' => $asset->branch?->name ?? 'Cabang',
                'name' => $asset->name,
                'purchase_date' => $asset->purchase_date?->format('Y-m-d'),
                'acquisition_value' => $acquisitionValue,
                'accumulated_depreciation' => $accumulatedDepreciation,
                'book_value' => $bookValue,
                'salvage_value' => $salvageValue,
                'useful_life_months' => $usefulLife,
                'monthly_depreciation' => $monthly,
                'months_elapsed' => $monthsElapsed,
                'scheduled_depreciation' => $scheduled,
                'unposted_depreciation' => round($scheduled - $accumulatedDepreciation, 2),
                'status' => $asset->status,
            ];
        });
    }
}
