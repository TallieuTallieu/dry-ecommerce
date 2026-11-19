<?php

declare(strict_types=1);

namespace Tests\Support;

use Tnt\Ecommerce\Contracts\CouponInterface;
use Tnt\Ecommerce\Contracts\DiscountableByCouponInterface;
use Tnt\Ecommerce\Contracts\TaxableInterface;
use Tnt\Ecommerce\Contracts\TaxRateInterface;
use Tnt\Ecommerce\Model\Coupon;
use Tnt\Ecommerce\Discount\CouponType;

/**
 * A deposit a voucher may pay and a percentage may not: it answers per
 * coupon, and takes no discount where no coupon asks.
 */
final class FakeVoucherDepositBuyable extends FakeBuyable implements
    DiscountableByCouponInterface,
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

    public function takesDiscountFrom(CouponInterface $coupon): bool
    {
        return $coupon instanceof Coupon &&
            $coupon->getType() === CouponType::Fixed;
    }

    public function getTaxRate(): TaxRateInterface
    {
        return $this->taxRate;
    }
}
