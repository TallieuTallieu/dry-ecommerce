<?php

declare(strict_types=1);

namespace Tnt\Ecommerce\Order;

use dry\db\Connection;
use Tnt\Ecommerce\Contracts\OrderReferenceInterface;
use Tnt\Ecommerce\Model\Order;

/**
 * Numbers orders per year: the year's last two digits, then a counter that
 * starts at 1 every January — `2600001`, `2600002`, …, `2700001`. Opt in with
 * `ecommerce.order_reference`. See docs/orders.md#yearly-numbers.
 *
 * Guessable by design, so a URL names the order by its uuid, never by this.
 */
class YearlyOrderReference implements OrderReferenceInterface
{
    /**
     * The counter's width. A year past 99999 orders widens it rather than
     * stopping a checkout: `26100000` is longer, but still never repeats.
     */
    private const DIGITS = 5;

    /**
     * @param Order $order
     * @return string
     */
    public function reference(Order $order): string
    {
        $year = $this->year();

        return sprintf(
            '%02d%0' . self::DIGITS . 'd',
            $year % 100,
            $this->next($year)
        );
    }

    /**
     * The year the order is placed in, in PHP's default timezone — the
     * shop's. Not `Order.created`: a draft started on 31 December and placed
     * on 1 January belongs to the new year.
     *
     * @return int
     */
    protected function year(): int
    {
        return (int) date('Y');
    }

    /**
     * The year's next number, from `ecommerce_order_sequence`. One statement
     * claims it: the upsert takes the row lock, and LAST_INSERT_ID(expr)
     * hands the claimed value back to this connection only — two checkouts
     * at the same moment can never read the same number. A placement that
     * fails after this leaves a gap; numbers are never handed out twice.
     *
     * @param int $year
     * @return int
     */
    protected function next(int $year): int
    {
        $db = Connection::get();

        $db->db_query(
            'INSERT INTO `ecommerce_order_sequence` (`year`, `last`) ' .
                'VALUES (?, LAST_INSERT_ID(1)) ' .
                'ON DUPLICATE KEY UPDATE `last` = LAST_INSERT_ID(`last` + 1)',
            $year
        );

        $number = $db->query_field('SELECT LAST_INSERT_ID()');

        if (!is_numeric($number) || (int) $number < 1) {
            throw new \UnexpectedValueException(
                'ecommerce_order_sequence handed back no number for ' . $year
            );
        }

        return (int) $number;
    }
}
