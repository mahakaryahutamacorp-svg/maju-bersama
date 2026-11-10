<?php

namespace App\Casts;

use App\Support\DecimalQuantity;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * @implements CastsAttributes<int|string, string>
 */
class QuantityCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): int|string
    {
        return DecimalQuantity::present($value);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): string
    {
        return DecimalQuantity::normalize($value);
    }
}
