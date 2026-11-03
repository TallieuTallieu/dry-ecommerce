<?php

declare(strict_types=1);

namespace Tnt\Ecommerce\Admin;

use dry\admin\component\DateView;
use dry\admin\component\StringView;
use dry\admin\Module;
use dry\orm\Index;
use dry\orm\Manager;
use dry\orm\sort\StaticSorter;
use Tnt\Ecommerce\Model\CouponRedemption;
use Tnt\Ecommerce\Money;

/**
 * The orders one coupon was spent on, inlined on the coupon's Redemptions
 * page by {@see CouponManager}. Read-only: {@see \Tnt\Ecommerce\Model\Coupon::redeem()}
 * is the one writer. See docs/admin.md#coupons.
 */
class CouponRedemptionManager extends Manager
{
    public function __construct()
    {
        parent::__construct(CouponRedemption::class, [
            'id' => 'coupon_redemptions',
            'title' => 'Redemptions',
            'singular' => 'redemption',
            'plural' => 'redemptions',
            'icon' => Module::ICON_RECEIPT,
        ]);

        $this->index = Index::create()
            ->add_component(
                DateView::create('created', [
                    'header' => 'Redeemed',
                    'format' => 'd/m/Y H:i',
                ])
            )
            ->add_component(
                StringView::create('order', [
                    'header' => 'Order',
                    'value' => self::order(...),
                ])
            )
            ->add_component(
                StringView::create('amount', [
                    'header' => 'Amount',
                    'value' => self::amount(...),
                ])
            )
            ->add_component(
                StringView::create('shortfall', [
                    'header' => 'Not covered',
                    'value' => self::shortfall(...),
                ])
            )
            ->set_sorter(new StaticSorter('id', StaticSorter::DESC));
    }

    /**
     * The order's row id, and its public reference once it has one.
     *
     * @param CouponRedemption $redemption
     * @return string
     */
    public static function order(CouponRedemption $redemption): string
    {
        $order = $redemption->order;

        if ($order === null) {
            return '';
        }

        $reference = (string) $order->order_id;

        return $reference === ''
            ? (string) $order->id
            : $order->id . ' · ' . $reference;
    }

    /**
     * @param CouponRedemption $redemption
     * @return string
     */
    public static function amount(CouponRedemption $redemption): string
    {
        return Money::toDecimal($redemption->getAmount());
    }

    /**
     * What the coupon could not cover, or "claimed" while it is unsettled.
     *
     * @param CouponRedemption $redemption
     * @return string
     */
    public static function shortfall(CouponRedemption $redemption): string
    {
        $shortfall = $redemption->getShortfall();

        return $shortfall === null ? 'claimed' : Money::toDecimal($shortfall);
    }
}
