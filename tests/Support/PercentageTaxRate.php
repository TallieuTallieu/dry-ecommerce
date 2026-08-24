<?php

declare(strict_types=1);

namespace Tests\Support;

use Tnt\Ecommerce\Contracts\TaxRateInterface;
use Tnt\Ecommerce\Money;

/**
 * VAT at a percentage — 6, 12 or 21 in Belgium.
 *
 * The package ships no rate of its own — the one it used to ship taxed nothing,
 * and existed only to satisfy a contract that has stopped asking — so this is
 * what a project's own rate is expected to look like: the rate is applied to the
 * amount it was handed, once, through {@see Money::percentageOf()}.
 *
 * It taxes the amount *on top of* it, which is the reading a shop quoting prices
 * without VAT wants. A shop quoting VAT-inclusive consumer prices writes a rate
 * that extracts instead, and that choice being the rate's rather than the cart's
 * is why {@see \Tnt\Ecommerce\Cart\Cart::getTax()} reports a figure and does not
 * touch a total.
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
