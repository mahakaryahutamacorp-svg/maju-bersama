<?php

namespace Tests\Unit\Enums;

use App\Enums\PaymentType;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PaymentTypeTest extends TestCase
{
    /**
     * @return array<string, array{0: ?string, 1: ?PaymentType}>
     */
    public static function legacyMethods(): array
    {
        return [
            'cash' => ['cash', PaymentType::Cash],
            'Cash' => ['Cash', PaymentType::Cash],
            'tunai' => ['tunai', PaymentType::Cash],
            'Tunai' => ['Tunai', PaymentType::Cash],
            'tempo' => ['tempo', PaymentType::Credit],
            'piutang' => ['piutang', PaymentType::Credit],
            'kredit' => ['kredit', PaymentType::Credit],
            'transfer lama tidak dipetakan' => ['transfer', null],
            'qris lama tidak dipetakan' => ['qris', null],
            'string kosong' => ['', null],
            'null' => [null, null],
        ];
    }

    #[DataProvider('legacyMethods')]
    public function test_from_legacy_maps_old_payment_methods(?string $method, ?PaymentType $expected): void
    {
        $this->assertSame($expected, PaymentType::fromLegacy($method));
    }
}
