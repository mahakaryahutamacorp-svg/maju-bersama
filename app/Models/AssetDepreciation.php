<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetDepreciation extends Model
{
    protected $fillable = [
        'fixed_asset_id',
        'depreciation_date',
        'amount',
        'journal_header_id',
    ];

    protected function casts(): array
    {
        return [
            'depreciation_date' => 'date',
            'amount' => 'decimal:4',
        ];
    }

    public function setDepreciationDateAttribute($value): void
    {
        $this->attributes['depreciation_date'] = \Carbon\Carbon::parse($value)->format('Y-m-d');
    }

    public function fixedAsset(): BelongsTo
    {
        return $this->belongsTo(FixedAsset::class, 'fixed_asset_id');
    }

    public function journalHeader(): BelongsTo
    {
        return $this->belongsTo(JournalHeader::class, 'journal_header_id');
    }
}
