<?php

declare(strict_types=1);

namespace Tests\Support;

use Tnt\Ecommerce\Contracts\DiscountableInterface;
use Tnt\Ecommerce\Contracts\TaxableInterface;
use Tnt\Ecommerce\Contracts\TaxRateInterface;

/**
 * A buyable that takes no discount — a deposit — and carries tax, so a test
 * can see it is taxed on its whole price whatever coupon is in force. Counts
 * how often it is asked, so a test can see a cart without a coupon never asks.
 */
final class FakeUndiscountedBuyable extends FakeBuyable implements
    DiscountableInterface,
    TaxableInterface
{
    public int $asked = 0;

    public function __construct(
        string $id,
        int $price,
        private readonly TaxRateInterface $taxRate = new PercentageTaxRate(21)
    ) {
        parent::__construct($id, $price, 'A deposit');
    }

    public function takesDiscount(): bool
    {
        $this->asked++;

        return false;
    }

    public function getTaxRate(): TaxRateInterface
    {
        return $this->taxRate;
    }
}
