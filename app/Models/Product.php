<?php

namespace App\Models;

use App\Traits\HasBranchScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Product extends Model
{
    use HasBranchScope;

    protected $fillable = [
        'branch_id',
        'category_id',
        'sku',
        'name',
        'unit',
        'purchase_price',
        'selling_price',
        'stock',
    ];

    protected function casts(): array
    {
        return [
            'purchase_price' => 'decimal:2',
            'selling_price' => 'decimal:2',
            'stock' => 'integer',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function saleItems(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    public function productPrices(): HasMany
    {
        return $this->hasMany(ProductPrice::class);
    }

    /**
     * Stock rows for this product. A product belongs to a single branch catalogue,
     * so in practice this resolves to one row, but the relation is kept as HasMany
     * to mirror the table structure.
     */
    public function inventories(): HasMany
    {
        return $this->hasMany(Inventory::class);
    }

    /**
     * The authoritative stock row for this product within its own branch.
     */
    public function inventory(): HasOne
    {
        return $this->hasOne(Inventory::class)
            ->where('inventories.branch_id', $this->branch_id);
    }

    /**
     * Current on-hand quantity taken from the inventories table, falling back to the
     * legacy products.stock column when no inventory row exists yet.
     */
    public function availableQuantity(): int
    {
        $inventory = Inventory::withoutGlobalScopes()
            ->where('branch_id', $this->branch_id)
            ->where('product_id', $this->id)
            ->first();

        return $inventory?->quantity ?? (int) $this->stock;
    }

    /**
     * Get the selling price for a given price level ID,
     * falling back to the legacy selling_price column if null or not found.
     */
    public function getPrice(?int $priceLevelId = null)
    {
        if ($priceLevelId !== null) {
            $productPrice = $this->relationLoaded('productPrices')
                ? $this->productPrices->firstWhere('price_level_id', $priceLevelId)
                : $this->productPrices()->where('price_level_id', $priceLevelId)->first();

            if ($productPrice !== null && $productPrice->price !== null) {
                return $productPrice->price;
            }
        }

        return $this->selling_price;
    }
}