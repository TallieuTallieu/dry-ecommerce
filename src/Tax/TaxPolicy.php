<?php

declare(strict_types=1);

namespace Tnt\Ecommerce\Tax;

use Tnt\Ecommerce\Contracts\TaxRateInterface;

/**
 * How a shop taxes: whether its prices contain tax, and what delivery costs.
 *
 * Two facts that belong to the shop rather than to any buyable, travelling
 * together because every tax question needs both. The cart takes one of these
 * instead of two loose settings, and asks it rather than branching on a
 * convention itself.
 *
 * Built from configuration in {@see \Tnt\Ecommerce\EcommerceServiceProvider}:
 * `ecommerce.prices` and `ecommerce.delivery_tax_rate`. The default is
 * inclusive prices and untaxed delivery, which is what a shop that has said
 * nothing gets — and, importantly, leaves its totals exactly where they were.
 */
final class TaxPolicy
{
    /**
     * @param PriceConvention $convention Whether prices contain their tax.
     * @param int|float|null $deliveryRate The rate on the fulfillment cost, as
     *                                     a percentage, or null for untaxed.
     */
    public function __construct(
        private readonly PriceConvention $convention = PriceConvention::Inclusive,
        private readonly int|float|null $deliveryRate = null
    ) {}

    /**
     * Whether this shop's prices contain their tax.
     *
     * @return PriceConvention
     */
    public function convention(): PriceConvention
    {
        return $this->convention;
    }

    /**
     * The tax on an amount at a buyable's rate, in cents.
     *
     * @param int $amount The amount as the shop quotes it, in cents.
     * @param TaxRateInterface $rate
     * @return int
     */
    public function taxOn(int $amount, TaxRateInterface $rate): int
    {
        return $this->convention->taxOn($amount, $rate->getPercentage());
    }

    /**
     * The tax on a fulfillment cost, in cents, or 0 when delivery is untaxed.
     *
     * One rate for the whole shop, rather than one per fulfillment method or
     * one derived from the cart.
     *
     * That is a deliberate simplification and it is worth knowing where it
     * stops being right. Belgian VAT treats delivery as ancillary to what is
     * being delivered, so a cart of 6% goods should carry 6% on its delivery
     * and a mixed cart should apportion it across the rates in it. A single
     * shop-wide rate reports the same figure whatever is in the cart, which is
     * exact for a shop selling at one rate and approximate for a shop selling
     * at several. Delivery is a small amount beside the goods, so the error is
     * small; a shop that cannot accept it needs delivery apportioned across
     * the cart, which this package does not do.
     *
     * @param int $cost The fulfillment cost, in cents.
     * @return int
     */
    public function taxOnDelivery(int $cost): int
    {
        if ($this->deliveryRate === null || $cost === 0) {
            return 0;
        }

        return $this->convention->taxOn($cost, $this->deliveryRate);
    }
}
