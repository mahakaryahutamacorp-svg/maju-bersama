<?php

namespace App\Models;

use App\Casts\QuantityCast;
use App\Traits\HasBranchScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasBranchScope;
    use SoftDeletes;

    /**
     * Line-item relations that make a product part of the ledger history.
     *
     * @var list<string>
     */
    public const TRANSACTION_RELATIONS = [
        'saleItems',
        'goodsReceiptItems',
        'purchaseOrderItems',
        'salesReturnItems',
        'purchaseReturnItems',
        'outgoingTransferItems',
        'incomingTransferItems',
    ];

    public const HISTORY_DELETE_MESSAGE = 'Gagal: Data ini tidak dapat dihapus karena sudah memiliki riwayat transaksi.';

    protected $fillable = [
        'branch_id',
        'category_id',
        'sku',
        'name',
        'unit',
        'purchase_price',
        'selling_price',
        'is_taxable',
        'stock',
    ];

    protected function casts(): array
    {
        return [
            'purchase_price' => 'decimal:2',
            'selling_price' => 'decimal:2',
            'is_taxable' => 'boolean',
            'stock' => QuantityCast::class,
        ];
    }

    protected static function booted(): void
    {
        static::deleting(function (Product $product) {
            $product->productPrices()->delete();
        });
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

    public function goodsReceiptItems(): HasMany
    {
        return $this->hasMany(GoodsReceiptItem::class);
    }

    public function purchaseOrderItems(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function outgoingTransferItems(): HasMany
    {
        return $this->hasMany(StockTransferItem::class, 'source_product_id');
    }

    public function incomingTransferItems(): HasMany
    {
        return $this->hasMany(StockTransferItem::class, 'destination_product_id');
    }

    public function hasTransactionHistory(): bool
    {
        foreach (self::TRANSACTION_RELATIONS as $relation) {
            $loaded = $this->getAttribute($relation.'_exists');

            if ($loaded !== null ? (bool) $loaded : $this->{$relation}()->exists()) {
                return true;
            }
        }

        return false;
    }

    public function prices(): HasMany
    {
        return $this->hasMany(ProductPrice::class);
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

    public function purchaseReturnItems(): HasMany
    {
        return $this->hasMany(PurchaseReturnItem::class);
    }

    public function salesReturnItems(): HasMany
    {
        return $this->hasMany(SalesReturnItem::class);
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
    public function availableQuantity(): int|string
    {
        $inventory = Inventory::withoutGlobalScopes()
            ->where('branch_id', $this->branch_id)
            ->where('product_id', $this->id)
            ->first();

        return $inventory?->quantity ?? $this->stock;
    }

    /**
     * Accessor agar $this->price mengembalikan nilai harga dasar (selling_price).
     */
    public function getPriceAttribute()
    {
        return $this->selling_price;
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

    /**
     * Helper untuk mengambil harga khusus sesuai grup pelanggan (Pricing Tier).
     * Urutan: harga khusus grup, lalu tingkat harga yang sepadan dengan grup
     * (grup "Grosir" memakai "Harga Grosir"), lalu $this->price (Harga Dasar/Umum).
     */
    public function getPriceForGroup(?int $groupId = null)
    {
        if ($groupId === null) {
            return $this->price;
        }

        $prices = match (true) {
            $this->relationLoaded('prices') => $this->prices,
            $this->relationLoaded('productPrices') => $this->productPrices,
            default => $this->prices()->get(),
        };

        $groupPrice = $prices->firstWhere('customer_group_id', $groupId);
        if ($groupPrice !== null && $groupPrice->price !== null) {
            return $groupPrice->price;
        }

        $priceLevelId = CustomerGroup::matchingPriceLevelId($groupId);
        if ($priceLevelId !== null) {
            $levelPrice = $prices->firstWhere('price_level_id', $priceLevelId);
            if ($levelPrice !== null && $levelPrice->price !== null) {
                return $levelPrice->price;
            }
        }

        return $this->price;
    }
}
