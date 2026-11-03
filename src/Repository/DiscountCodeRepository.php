<?php

namespace Tnt\Ecommerce\Repository;

use Tnt\Dbi\Criteria\Equals;
use Tnt\Ecommerce\Model\DiscountCode;

/**
 * Reads `ecommerce_discount_code`.
 *
 * The one thing a shop does with discount codes is turn the string a visitor
 * typed into a row, which is {@see byCode()}. Whether the coupon behind that
 * row is still redeemable is the coupon's business, not this query's.
 *
 * @extends Repository<DiscountCode>
 */
class DiscountCodeRepository extends Repository
{
    protected string $model = DiscountCode::class;

    /**
     * Filter by the code as typed — trimmed and uppercased the way codes are
     * stored ({@see DiscountCode::normalise()}), so `summer10` finds
     * `SUMMER10`.
     *
     * @param string $code
     * @return static
     */
    public function byCode(string $code): static
    {
        $this->addCriteria(new Equals('code', DiscountCode::normalise($code)));

        return $this;
    }

    /**
     * The code row behind one coupon.
     *
     * @param string $couponClass
     * @param int $couponId
     * @return static
     */
    public function forCoupon(string $couponClass, int $couponId): static
    {
        $this->addCriteria(new Equals('coupon_class', $couponClass));
        $this->addCriteria(new Equals('coupon_id', $couponId));

        return $this;
    }
}
