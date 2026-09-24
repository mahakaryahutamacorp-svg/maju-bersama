<?php

namespace App\Services\Reports;

use App\Models\FixedAsset;
use Illuminate\Support\Collection;

class FixedAssetReportService
{
    /**
     * Get fixed assets list with accumulated depreciation and book values.
     *
     * @param int|null $branchId
     * @return Collection
     */
    public function getAssets(?int $branchId = null): Collection
    {
        $query = FixedAsset::withoutGlobalScopes()
            ->withSum('depreciations', 'amount');

        if ($branchId !== null) {
            $query->where('branch_id', $branchId);
        }

        return $query->orderBy('id', 'asc')->get()->map(function (FixedAsset $asset) {
            $acquisitionValue = (float) $asset->purchase_price;
            $accumulatedDepreciation = (float) ($asset->depreciations_sum_amount ?? 0);
            $bookValue = $acquisitionValue - $accumulatedDepreciation;

            return (object) [
                'id' => $asset->id,
                'branch_id' => $asset->branch_id,
                'name' => $asset->name,
                'purchase_date' => $asset->purchase_date?->format('Y-m-d'),
                'acquisition_value' => $acquisitionValue,
                'accumulated_depreciation' => $accumulatedDepreciation,
                'book_value' => $bookValue,
                'salvage_value' => (float) $asset->salvage_value,
                'useful_life_months' => $asset->useful_life_months,
                'monthly_depreciation' => $asset->monthly_depreciation,
                'status' => $asset->status,
            ];
        });
    }
}
