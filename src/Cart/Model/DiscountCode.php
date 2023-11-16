<?php

namespace Tnt\Ecommerce\Model;

use dry\db\FetchException;
use dry\orm\Model;
use Tnt\Ecommerce\Contracts\CouponInterface;

class DiscountCode extends Model
{
    const TABLE = 'ecommerce_discount_code';

    public function get_coupon(): ?CouponInterface
    {
        $couponClass = $this->coupon_class;

        try {
            return $couponClass::load($this->coupon_id);
        } catch (FetchException $e) {
            return null;
        }
    }
}