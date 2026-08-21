<?php

namespace Tnt\Ecommerce\Contracts;

/**
 * A rate that turns an amount into an amount of tax.
 *
 * Both sides are integer cents. An implementation applying a real percentage —
 * Belgian VAT at 6%, 12% or 21% — is where fractional cents appear, so it is
 * expected to round with {@see \Tnt\Ecommerce\Money::percentageOf()}, which
 * rounds half away from zero, and to apply the rate to the smallest amount it
 * genuinely covers rather than to a total.
 *
 * @see \Tnt\Ecommerce\Money
 */
interface TaxRateInterface
{
    /**
     * @param int $amount The amount to tax, in cents.
     * @return int The tax due on it, in cents.
     */
    public function getTax(int $amount): int;
}
