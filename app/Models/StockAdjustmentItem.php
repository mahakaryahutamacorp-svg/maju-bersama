<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockAdjustmentItem extends Model
{
    protected $fillable = [
        'stock_adjustment_id',
        'product_id',
        'expected_qty',
        'actual_qty',
        'difference_qty',
        'unit_cost',
        'subtotal_value',
    ];

    protected $casts = [
        'expected_qty' => 'integer',
        'actual_qty' => 'integer',
        'difference_qty' => 'integer',
        'unit_cost' => 'decimal:2',
        'subtotal_value' => 'decimal:2',
    ];

    public function adjustment(): BelongsTo
    {
        return $this->belongsTo(StockAdjustment::class, 'stock_adjustment_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
