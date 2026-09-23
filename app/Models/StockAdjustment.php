<?php

namespace App\Models;

use App\Traits\HasBranchScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockAdjustment extends Model
{
    use HasBranchScope;

    protected $fillable = [
        'branch_id',
        'reference_number',
        'date',
        'adjustment_date',
        'notes',
        'journal_header_id',
        'total_loss_value',
        'total_gain_value',
    ];

    protected $casts = [
        'date'             => 'date',
        'adjustment_date'  => 'date',
        'total_loss_value' => 'decimal:2',
        'total_gain_value' => 'decimal:2',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(StockAdjustmentLine::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(StockAdjustmentItem::class);
    }

    public function journal(): BelongsTo
    {
        return $this->belongsTo(JournalHeader::class, 'journal_header_id');
    }
}
