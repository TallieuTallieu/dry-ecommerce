<?php

declare(strict_types=1);

namespace Tnt\Ecommerce\Admin;

use dry\orm\Manager;
use dry\orm\search\LikeSearcher;
use Tnt\Ecommerce\Model\Coupon;
use Tnt\Ecommerce\Model\DiscountCode;

/**
 * Finds a coupon by its code — which lives in `ecommerce_discount_code`, so
 * through a subquery — or by its note.
 */
class CouponSearcher extends LikeSearcher
{
    public function __construct()
    {
        parent::__construct(['note']);
    }

    /**
     * @param Manager $manager
     * @param mixed $query
     * @return array<int, mixed> The WHERE clause, then its parameters.
     */
    public function get_sql_test($manager, $query): array
    {
        $text = is_scalar($query) ? (string) $query : '';

        [$noteTest, $noteParam] = parent::get_sql_test($manager, $text);

        return [
            '`' .
            Coupon::TABLE .
            '`.`id` IN (SELECT `coupon_id` FROM `' .
            DiscountCode::TABLE .
            '` WHERE `coupon_class` = ? AND `code` LIKE ?) OR ' .
            $noteTest,
            Coupon::class,
            '%' . DiscountCode::normalise($text) . '%',
            $noteParam,
        ];
    }
}
