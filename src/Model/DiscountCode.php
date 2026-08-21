<?php

namespace Tnt\Ecommerce\Model;

use dry\db\FetchException;
use dry\orm\Model;
use Tnt\Ecommerce\Contracts\CouponInterface;

/**
 * A redeemable code, as stored in `ecommerce_discount_code`.
 *
 * The code itself carries no discount logic: it points at a coupon — any model
 * the project makes implement {@see CouponInterface} — which decides whether it
 * is redeemable and by how much.
 *
 * @property int|null $id
 * @property int $created
 * @property int $updated
 * @property int $coupon_id
 * @property string $coupon_class
 * @property string $code
 * @property-read CouponInterface|null $coupon
 */
class DiscountCode extends Model
{
    const TABLE = 'ecommerce_discount_code';

    public function get_coupon(): ?CouponInterface
    {
        /** @var class-string<Model&CouponInterface> $couponClass */
        $couponClass = $this->coupon_class;

        try {
            return $couponClass::load($this->coupon_id);
        } catch (FetchException $e) {
            return null;
        }
    }
}
