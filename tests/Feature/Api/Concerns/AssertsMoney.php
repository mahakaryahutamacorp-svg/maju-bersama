<?php

namespace Tests\Feature\Api\Concerns;

trait AssertsMoney
{
    protected function money(mixed $amount): string
    {
        return bcadd((string) $amount, '0', 2);
    }

    protected function assertMoneySame(mixed $left, mixed $right): void
    {
        $this->assertSame(
            0,
            bccomp($this->money($left), $this->money($right), 2),
            "Expected {$this->money($left)} to equal {$this->money($right)} at scale 2.",
        );
    }

    protected function assertMoneyNotHundredfold(mixed $actual, mixed $base): void
    {
        $this->assertNotSame(
            0,
            bccomp($this->money($actual), bcmul($this->money($base), '100', 2), 2),
        );
    }
}
