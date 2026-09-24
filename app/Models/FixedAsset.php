<?php

namespace App\Models;

use App\Traits\HasBranchScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FixedAsset extends Model
{
    use HasBranchScope;

    protected $fillable = [
        'branch_id',
        'name',
        'purchase_date',
        'purchase_price',
        'salvage_value',
        'useful_life_months',
        'asset_account_id',
        'depreciation_account_id',
        'expense_account_id',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'purchase_date' => 'date',
            'purchase_price' => 'decimal:4',
            'salvage_value' => 'decimal:4',
            'useful_life_months' => 'integer',
        ];
    }

    public function setPurchaseDateAttribute($value): void
    {
        $this->attributes['purchase_date'] = \Carbon\Carbon::parse($value)->format('Y-m-d');
    }

    /**
     * Hitung nilai penyusutan bulanan metode Garis Lurus:
     * (purchase_price - salvage_value) / useful_life_months
     */
    public function getMonthlyDepreciationAttribute(): float
    {
        $months = (int) $this->useful_life_months;
        if ($months <= 0) {
            return 0.0;
        }

        $depreciableBase = (float) $this->purchase_price - (float) $this->salvage_value;
        if ($depreciableBase <= 0) {
            return 0.0;
        }

        return round($depreciableBase / $months, 4);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function assetAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'asset_account_id');
    }

    public function depreciationAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'depreciation_account_id');
    }

    public function expenseAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'expense_account_id');
    }

    public function depreciations(): HasMany
    {
        return $this->hasMany(AssetDepreciation::class, 'fixed_asset_id');
    }
}
