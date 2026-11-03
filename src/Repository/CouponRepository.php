<?php

declare(strict_types=1);

namespace Tnt\Ecommerce\Repository;

use Tnt\Ecommerce\Model\Coupon;

/**
 * Reads `ecommerce_coupon`. A coupon is found by its code through
 * {@see DiscountCodeRepository::byCode()}, which is how a customer names one.
 *
 * @extends Repository<Coupon>
 */
class CouponRepository extends Repository
{
    protected string $model = Coupon::class;
}
