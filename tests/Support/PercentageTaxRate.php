<?php

declare(strict_types=1);

namespace Tests\Support;

use Tnt\Ecommerce\Contracts\TaxRateInterface;
use Tnt\Ecommerce\Money;

/**
 * VAT at a percentage — 6, 12 or 21 in Belgium.
 *
 * The package ships no percentage rate of its own (`NullTaxRate` is the only
 * implementation, and tax is not wired into the cart), so this is what a
 * project's own rate is expected to look like: the rate is applied to the
 * amount it was handed, once, through {@see Money::percentageOf()}.
 */
final class PercentageTaxRate implements TaxRateInterface
{
    /**
     * @param int|float $percentage The rate, as a percentage: 21 means 21%.
     */
    public function __construct(private readonly int|float $percentage) {}

    /**
     * @param int $amount
     * @return int
     */
    public function getTax(int $amount): int
    {
        return Money::percentageOf($amount, $this->percentage);
    }
}
