<?php

declare(strict_types=1);

namespace Tnt\Ecommerce\Repository;

use Tnt\Dbi\Criteria\Equals;
use Tnt\Dbi\Criteria\OrderBy;
use Tnt\Ecommerce\Model\Coupon;
use Tnt\Ecommerce\Model\CouponRedemption;

/**
 * Reads `ecommerce_coupon_redemption`, oldest first.
 *
 * @extends Repository<CouponRedemption>
 */
class CouponRedemptionRepository extends Repository
{
    protected string $model = CouponRedemption::class;

    protected function init(): void
    {
        $this->addCriteria(new OrderBy('id'));
    }

    /**
     * Every order one coupon was spent on.
     *
     * @param Coupon $coupon
     * @return static
     */
    public function forCoupon(Coupon $coupon): static
    {
        $this->addCriteria(new Equals('coupon', $coupon->id));

        return $this;
    }
}
