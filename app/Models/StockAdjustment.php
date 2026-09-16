<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class StockAdjustment extends Model
{
    protected $fillable = [
        'branch_id',
        'reference_number',
        'date',
        'notes',
        'total_loss_value',
        'total_gain_value',
    ];

    protected $casts = [
        'date' => 'date',
        'total_loss_value' => 'decimal:2',
        'total_gain_value' => 'decimal:2',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(StockAdjustmentItem::class);
    }

    public function journal(): HasOne
    {
        return $this->hasOne(JournalHeader::class, 'reference_number', 'reference_number');
    }
}
