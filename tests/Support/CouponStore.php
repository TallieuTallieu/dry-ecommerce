<?php

declare(strict_types=1);

namespace Tests\Support;

use Tnt\Ecommerce\Model\DiscountCode;

/**
 * The two coupon tables and the code table, in memory, shared by every
 * {@see InMemoryCoupon} pointed at it — so two instances of one coupon, as
 * two requests would load it, write to the same row.
 */
final class CouponStore
{
    /**
     * The columns redeem() writes, per coupon id.
     *
     * @var array<int, array{balance: int, uses: int}>
     */
    public array $coupons = [];

    /**
     * Per order id — UNIQUE(order), as the table has it.
     *
     * @var array<int, array{id: int, coupon: int, amount: int, shortfall: int|null}>
     */
    public array $redemptions = [];

    /**
     * Code rows, per coupon id.
     *
     * @var array<int, DiscountCode>
     */
    public array $codes = [];

    public int $lastCouponId = 0;

    public int $lastRedemptionId = 0;

    /**
     * @param string $code
     * @return bool
     */
    public function holds(string $code): bool
    {
        foreach ($this->codes as $row) {
            if ($row->code === $code) {
                return true;
            }
        }

        return false;
    }
}
