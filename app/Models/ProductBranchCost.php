<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductBranchCost extends Model
{
    protected $fillable = [
        'entity_id',
        'branch_id',
        'product_id',
        'qty_on_hand',
        'avg_unit_cost',
        'inventory_value',
        'last_movement_at',
        'version',
    ];

    protected function casts(): array
    {
        return [
            'qty_on_hand' => 'decimal:4',
            'avg_unit_cost' => 'decimal:4',
            'inventory_value' => 'decimal:2',
            'last_movement_at' => 'datetime',
            'version' => 'integer',
        ];
    }

    public function entity(): BelongsTo
    {
        return $this->belongsTo(Entity::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
