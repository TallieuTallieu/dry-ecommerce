<?php

namespace Tnt\Ecommerce\Contracts;

/**
 * A rate that turns an amount into an amount of tax.
 *
 * Reached through {@see TaxableInterface::getTaxRate()}. The package ships no
 * implementation: the one it used to ship, `NullTaxRate`, existed only so that
 * an untaxed buyable could satisfy a contract that has since stopped asking, and
 * a real rate is the shop's — it is the shop that knows whether its prices are
 * quoted with VAT in them.
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
