<?php

declare(strict_types=1);

namespace Tnt\Ecommerce\Model;

use dry\orm\Model;

/**
 * One coupon spent on one paid order, `ecommerce_coupon_redemption`. Written
 * by {@see Coupon::redeem()} alone; UNIQUE(order), so an order redeems once.
 * See docs/discounts.md#redemption.
 *
 * @property int|null $id
 * @property int $created
 * @property Coupon|null $coupon
 * @property Order|null $order
 * @property int $amount What the order took off, in cents — the quote,
 *                       honoured.
 * @property int|null $shortfall What the coupon could not cover, in cents;
 *                               NULL while claimed but not settled.
 */
class CouponRedemption extends Model
{
    const TABLE = 'ecommerce_coupon_redemption';

    /**
     * @var array<string, string>
     */
    public static $special_fields = [
        'coupon' => Coupon::class,
        'order' => Order::class,
    ];

    /**
     * @return int
     */
    public function getAmount(): int
    {
        return (int) $this->amount;
    }

    /**
     * Null: claimed, not settled.
     *
     * @return int|null
     */
    public function getShortfall(): ?int
    {
        return $this->shortfall === null ? null : (int) $this->shortfall;
    }
}
