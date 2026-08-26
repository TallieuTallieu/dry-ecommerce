<?php

namespace Tnt\Ecommerce\Contracts;

/**
 * A buyable that carries tax. Opt in and the buyable's lines count towards
 * {@see CartInterface::getTax()}; leave it out and they contribute nothing.
 * See docs/tax.md.
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
