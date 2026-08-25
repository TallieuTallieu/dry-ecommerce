<?php

namespace Tnt\Ecommerce\Contracts;

/**
 * A rate of tax, as a percentage.
 *
 * Belgian VAT is 6, 12 or 21. A rate says which; it does not work out what
 * that comes to. {@see \Tnt\Ecommerce\Money} owns that arithmetic, and
 * {@see \Tnt\Ecommerce\Tax\PriceConvention} owns the half of it that depends
 * on whether the shop's prices already contain the tax.
 *
 * # Why this asks for the rate and not the amount
 *
 * It used to be `getTax(int $amount): int` — hand it an amount, get the tax on
 * it. That could not survive tax-inclusive pricing. The two conventions need
 * `amount x r/100` and `amount x r/(100 + r)` respectively, and given only a
 * finished figure the package cannot tell which one it was handed, nor convert
 * between them: the rate is not recoverable from the answer once it has been
 * rounded to the cent.
 *
 * Every implementation would therefore have had to branch on the convention
 * itself, reimplementing the same two formulas and the same rounding rule, in
 * every shop, correctly. Asking for the rate instead means a shop states the
 * one thing it knows — "this is taxed at 21" — and the package applies the
 * rule it documents in {@see \Tnt\Ecommerce\Money} exactly once.
 *
 * @see TaxableInterface
 * @see \Tnt\Ecommerce\Tax\PriceConvention
 */
interface TaxRateInterface
{
    /**
     * The rate, as a percentage: 21 means 21%.
     *
     * Honoured to four decimal places of a percent, so a rate of 21, 21.5 or
     * 0.0625 all land where they should. 0 is a rate that costs nothing, which
     * is different from a buyable that is not taxable at all — that one does
     * not implement {@see TaxableInterface}.
     *
     * @return int|float
     */
    public function getPercentage(): int|float;
}
