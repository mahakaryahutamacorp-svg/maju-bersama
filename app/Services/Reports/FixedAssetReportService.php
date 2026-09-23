<?php

namespace App\Services\Reports;

use Illuminate\Support\Collection;

class FixedAssetReportService
{
    /**
     * Get fixed assets list (Graceful placeholder until fixed_assets schema is migrated).
     *
     * @param int|null $branchId
     * @return Collection
     */
    public function getAssets(?int $branchId = null): Collection
    {
        return collect([]);
    }
}
