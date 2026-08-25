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
 * inclusive prices and delivery at 0%, which is what a shop that has said
 * nothing gets — and, importantly, leaves its totals exactly where they were.
 */
final class TaxPolicy
{
    /**
     * @param PriceConvention $convention Whether prices contain their tax.
     * @param int|float $deliveryRate The rate on the fulfillment cost, as a
     *                                percentage. 0, the default, is delivery
     *                                that costs no tax.
     */
    public function __construct(
        private readonly PriceConvention $convention = PriceConvention::Inclusive,
        private readonly int|float $deliveryRate = 0
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
     * Whether tax under this policy belongs in the total.
     *
     * The same question {@see PriceConvention::addsTaxToTheTotal()} answers,
     * asked here so that a caller holding a policy does not have to reach
     * through it for the convention to ask. What a shop does with tax is the
     * policy's to say; which of the two conventions produced that answer is
     * nobody else's business.
     *
     * @return bool
     */
    public function addsTaxToTheTotal(): bool
    {
        return $this->convention->addsTaxToTheTotal();
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
     * The tax on a fulfillment cost, in cents.
     *
     * One rate for the whole shop, rather than one per fulfillment method or
     * one derived from the cart. A shop that sets no rate delivers at 0%, and
     * 0% of any cost is 0 cents, so untaxed delivery needs no case of its own
     * here — it is the ordinary path with the ordinary rate.
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
        return $this->convention->taxOn($cost, $this->deliveryRate);
    }
}
