<?php

namespace Tnt\Ecommerce\Contracts;

/**
 * A rate of tax, as a percentage. A rate states the rate, not the amount —
 * the arithmetic belongs to {@see \Tnt\Ecommerce\Money} and
 * {@see \Tnt\Ecommerce\Tax\PriceConvention}. See docs/tax.md.
 *
 * @see TaxableInterface
 * @see \Tnt\Ecommerce\Tax\PriceConvention
 */
interface TaxRateInterface
{
    /**
     * The rate, as a percentage: 21 means 21%. Honoured to four decimal
     * places of a percent.
     *
     * @return int|float
     */
    public function getPercentage(): int|float;
}
