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
 * is redeemable and by how much. Codes are stored trimmed and uppercase, so a
 * customer may type one in any case; the column is unique. See
 * docs/discounts.md#codes.
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

    /**
     * A code the way it is stored and looked up: trimmed, uppercase.
     *
     * @param string $code
     * @return string
     */
    public static function normalise(string $code): string
    {
        return strtoupper(trim($code));
    }

    /**
     * @return void
     */
    public function save()
    {
        $this->code = self::normalise((string) $this->code);

        parent::save();
    }

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
