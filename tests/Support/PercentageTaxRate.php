<?php

declare(strict_types=1);

namespace Tests\Support;

use Tnt\Ecommerce\Contracts\TaxRateInterface;

/**
 * VAT at a percentage — 6, 12 or 21 in Belgium.
 *
 * The package ships no rate of its own, so this is what a project's own rate
 * looks like: it states the percentage and stops. Working out what that comes
 * to belongs to {@see \Tnt\Ecommerce\Money}, and whether the amount already
 * contains it belongs to {@see \Tnt\Ecommerce\Tax\PriceConvention} — neither
 * is a decision a rate is in any position to make.
 *
 * This used to compute the tax itself, with `Money::percentageOf()`. That was
 * only ever correct for a shop quoting net prices, and every implementation
 * would have had to learn the shop's convention to fix it. Stating the rate is
 * the whole job now.
 */
final class PercentageTaxRate implements TaxRateInterface
{
    /**
     * @param int|float $percentage The rate, as a percentage: 21 means 21%.
     */
    public function __construct(private readonly int|float $percentage) {}

    /**
     * @return int|float
     */
    public function getPercentage(): int|float
    {
        return $this->percentage;
    }
}
