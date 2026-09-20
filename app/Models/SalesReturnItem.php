<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesReturnItem extends Model
{
    protected $fillable = [
        'sales_return_id',
        'product_id',
        'quantity',
        'unit_price',
        'unit_cost',
        'subtotal',
        'subtotal_cost',
    ];

    protected function casts(): array
    {
        return [
            'quantity'      => 'integer',
            'unit_price'    => 'decimal:2',
            'unit_cost'     => 'decimal:2',
            'subtotal'      => 'decimal:2',
            'subtotal_cost' => 'decimal:2',
        ];
    }

    public function salesReturn(): BelongsTo
    {
        return $this->belongsTo(SalesReturn::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
