<?php

declare(strict_types=1);

namespace Tests\Support;

use Tnt\Ecommerce\Contracts\CouponInterface;
use Tnt\Ecommerce\Model\DiscountCode;

/**
 * A discount code carrying a coupon that was handed to it rather than loaded.
 *
 * `DiscountCode` is a dry model, but constructing one touches nothing: dry's
 * `Model` is a thin wrapper over an array and only reaches for a connection
 * inside its query methods. Only the coupon lookup does I/O, and that is what
 * this overrides — which is the whole reason a cart carrying a discount can be
 * tested without a database.
 */
final class FakeDiscountCode extends DiscountCode
{
    private ?CouponInterface $fakeCoupon = null;

    public static function withCoupon(CouponInterface $coupon): self
    {
        $code = new self();
        $code->fakeCoupon = $coupon;

        return $code;
    }

    public static function withoutCoupon(): self
    {
        return new self();
    }

    public function get_coupon(): ?CouponInterface
    {
        return $this->fakeCoupon;
    }
}
