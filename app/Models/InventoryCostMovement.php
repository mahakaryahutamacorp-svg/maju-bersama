<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryCostMovement extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'entity_id',
        'branch_id',
        'product_id',
        'movement_type',
        'source_type',
        'source_id',
        'qty',
        'unit_cost',
        'value',
        'qty_after',
        'avg_cost_after',
        'value_after',
        'journal_entry_id',
    ];

    protected function casts(): array
    {
        return [
            'qty' => 'decimal:4',
            'unit_cost' => 'decimal:4',
            'value' => 'decimal:2',
            'qty_after' => 'decimal:4',
            'avg_cost_after' => 'decimal:4',
            'value_after' => 'decimal:2',
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
