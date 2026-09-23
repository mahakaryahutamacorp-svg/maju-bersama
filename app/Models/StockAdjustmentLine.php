<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockAdjustmentLine extends Model
{
    protected $fillable = [
        'stock_adjustment_id',
        'product_id',
        'system_qty',
        'actual_qty',
        'difference_qty',
        'unit_cost',
        'subtotal_cost',
        'reason',
    ];

    protected $casts = [
        'system_qty'     => 'integer',
        'actual_qty'     => 'integer',
        'difference_qty' => 'integer',
        'unit_cost'      => 'decimal:4',
        'subtotal_cost'  => 'decimal:4',
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
