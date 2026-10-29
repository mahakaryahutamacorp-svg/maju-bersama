<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Entity extends Model
{
    public const CODE_MB_PUPUK = 'MB-PUPUK';

    public const CODE_DEKA = 'DEKA';

    protected $fillable = [
        'code',
        'legal_name',
        'npwp',
        'base_currency',
        'cash_shortage_auto_threshold',
        'cash_overage_auto_threshold',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'cash_shortage_auto_threshold' => 'decimal:2',
            'cash_overage_auto_threshold' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }

    public function accounts(): HasMany
    {
        return $this->hasMany(ChartOfAccount::class);
    }

    public function accountMappings(): HasMany
    {
        return $this->hasMany(AccountMapping::class);
    }
}
