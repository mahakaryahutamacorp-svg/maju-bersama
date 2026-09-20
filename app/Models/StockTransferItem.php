<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockTransferItem extends Model
{
    protected $fillable = [
        'stock_transfer_id',
        'source_product_id',
        'destination_product_id',
        'quantity',
        'unit_cost',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_cost' => 'decimal:2',
        ];
    }

    public function stockTransfer(): BelongsTo
    {
        return $this->belongsTo(StockTransfer::class);
    }

    public function sourceProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'source_product_id');
    }

    public function product(): BelongsTo
    {
        return $this->sourceProduct();
    }

    public function destinationProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'destination_product_id');
    }
}
