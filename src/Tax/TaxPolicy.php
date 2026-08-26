<?php

declare(strict_types=1);

namespace Tnt\Ecommerce\Tax;

use Tnt\Ecommerce\Contracts\TaxRateInterface;

/**
 * How a shop taxes: whether its prices contain tax, and the rate on delivery.
 * Built from `ecommerce.prices` and `ecommerce.delivery_tax_rate`; the
 * defaults (inclusive, 0%) leave an existing shop's totals unmoved. See
 * docs/tax.md.
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
     * The tax on a fulfillment cost, in cents. One shop-wide rate — exact for
     * a shop selling at one rate, approximate for a mixed cart; see
     * docs/tax.md for where that stops being right.
     *
     * @param int $cost The fulfillment cost, in cents.
     * @return int
     */
    public function taxOnDelivery(int $cost): int
    {
        return $this->convention->taxOn($cost, $this->deliveryRate);
    }
}
