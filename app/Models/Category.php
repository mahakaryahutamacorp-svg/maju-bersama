<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    public const CATALOG_PUPUK = 'pupuk';

    public const CATALOG_PAKAN = 'pakan';

    /**
     * Kategori yang biasa dipakai di toko pakan.
     *
     * @var list<string>
     */
    public const PAKAN_NAMES = [
        'Pakan Ayam',
        'Pakan Bebek',
        'Pakan Sapi',
        'Pakan Kambing',
        'Pakan Ikan',
        'Konsentrat',
        'Dedak & Bekatul',
        'Jagung',
        'Vitamin & Obat Ternak',
        'Peralatan Ternak',
    ];

    protected $fillable = [
        'name',
        'catalog',
    ];

    public function scopeForCatalog($query, string $catalog)
    {
        if ($catalog === self::CATALOG_PAKAN) {
            return $query->where('catalog', self::CATALOG_PAKAN);
        }

        return $query->where(function ($inner) {
            $inner->where('catalog', self::CATALOG_PUPUK)->orWhereNull('catalog');
        });
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
