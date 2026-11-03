<?php

declare(strict_types=1);

namespace Tests\Support;

use Tnt\Ecommerce\Contracts\DiscountableInterface;
use Tnt\Ecommerce\Contracts\TaxableInterface;
use Tnt\Ecommerce\Contracts\TaxRateInterface;

/**
 * A buyable that takes no discount — a deposit — and carries tax, so a test
 * can see it is taxed on its whole price whatever coupon is in force.
 */
final class FakeUndiscountedBuyable extends FakeBuyable implements
    DiscountableInterface,
    TaxableInterface
{
    public function __construct(
        string $id,
        int $price,
        private readonly TaxRateInterface $taxRate = new PercentageTaxRate(21)
    ) {
        parent::__construct($id, $price, 'A deposit');
    }

    public function takesDiscount(): bool
    {
        return false;
    }

    public function getTaxRate(): TaxRateInterface
    {
        return $this->taxRate;
    }
}
