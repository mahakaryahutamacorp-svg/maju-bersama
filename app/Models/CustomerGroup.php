<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CustomerGroup extends Model
{
    protected $fillable = [
        'name',
        'description',
        'notes',
    ];

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    public function productPrices(): HasMany
    {
        return $this->hasMany(ProductPrice::class);
    }

    /**
     * Tingkat harga yang namanya sepadan dengan grup ini, misalnya grup "Grosir"
     * memakai "Harga Grosir" bila produk tidak punya harga khusus grup.
     */
    public static function matchingPriceLevelId(int $groupId): ?int
    {
        return once(function () use ($groupId): ?int {
            $groupName = self::query()->whereKey($groupId)->value('name');
            if ($groupName === null) {
                return null;
            }

            $aliases = collect(explode('/', $groupName))
                ->map(fn (string $part) => self::normaliseTierName($part))
                ->filter()
                ->all();

            return PriceLevel::query()
                ->where('is_active', true)
                ->where('is_default', false)
                ->orderBy('id')
                ->get(['id', 'name'])
                ->first(fn (PriceLevel $level) => in_array(self::normaliseTierName($level->name), $aliases, true))
                ?->id;
        });
    }

    private static function normaliseTierName(string $name): string
    {
        $name = mb_strtolower(trim($name));

        return trim(preg_replace('/^harga\s+/', '', $name) ?? $name);
    }
}
