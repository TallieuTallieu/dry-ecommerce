<?php

namespace Tnt\Ecommerce\Contracts;

/**
 * A buyable that carries tax.
 *
 * Opt in and the buyable's lines count towards {@see CartInterface::getTax()};
 * leave it out and they contribute nothing to it. That is the whole of the
 * capability, and it is deliberately less than it sounds — see below.
 *
 * The reason it is an interface of its own is the same as for
 * {@see HasStockInterface}. `getTaxRate()` used to be mandatory on every
 * buyable, so a model that stores a plain VAT percentage, or none, had to
 * produce a `TaxRateInterface` anyway; the package shipped a `NullTaxRate`
 * returning 0 so that it could. Both are gone. A buyable with no rate says so by
 * not implementing this.
 *
 * # What the cart does with it, and what it does not
 *
 * {@see CartInterface::getTax()} sums the tax on the lines whose buyable
 * implements this, applying each rate to that line's total, once — the
 * per-line half of the rounding rule in {@see \Tnt\Ecommerce\Money}.
 *
 * It does **not** enter {@see CartInterface::getTotal()}, and no tax is written
 * to an order. Whether a price is quoted with tax included — as a Belgian
 * consumer price is — or without it, and therefore whether tax is a figure to
 * report or an amount to add, is a decision this package has never made and
 * cannot make on a shop's behalf. Until it does, `getTax()` reports and the
 * total is untouched, so a shop can print a VAT figure without any existing
 * total changing under it.
 *
 * @see TaxRateInterface
 */
interface TaxableInterface extends BuyableInterface
{
    /**
     * The rate that applies to this buyable.
     *
     * @return TaxRateInterface
     */
    public function getTaxRate(): TaxRateInterface;
}
