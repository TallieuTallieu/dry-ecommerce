<?php

declare(strict_types=1);

namespace Tnt\Ecommerce\Order;

use dry\db\Connection;
use dry\util\Period;
use Tnt\Ecommerce\Model\Order;
use Tnt\Ecommerce\Payment\PaymentStatus;

/**
 * Orders counted and added up over a {@see Period}, by the moment they were
 * placed, per bucket of the period: what a ranged dashboard widget shows.
 *
 * One query per period, however many buckets: MySQL's `INTERVAL()` sorts
 * each order into its bucket in the same pass that sums it, along the
 * `(state, created)` index. The year-earlier figures are a second query over
 * the period's comparison, never a query per bucket.
 *
 * Paid is {@see PaymentStatus::Paid} alone: a refunded order has given its
 * money back, and a partly refunded one is no longer worth its total.
 *
 * A shop that leaves more orders out — ones it canceled, ones whose payment
 * failed — says so in `$scope`, a WHERE clause and its parameters:
 *
 * ```php
 * new OrderStats(fn() => ['`canceled` IS NULL']);
 * ```
 *
 * @see docs/orders.md#figures-over-a-period
 */
final class OrderStats
{
    /** @var (\Closure(): array<int, string|int>)|null */
    private ?\Closure $scope;

    /**
     * @param (callable(): array<int, string|int>)|null $scope `[sql, ...parameters]`
     */
    public function __construct(?callable $scope = null)
    {
        $this->scope = $scope === null ? null : \Closure::fromCallable($scope);
    }

    /**
     * The period's figures, or with `$comparison` the year-earlier one's on
     * the same buckets.
     */
    public function figures(
        Period $period,
        bool $comparison = false
    ): OrderFigures {
        [$sql, $parameters] = $this->query($period, $comparison);
        $buckets = count($period->bounds()) - 1;

        $orders = array_fill(0, $buckets, 0);
        $paid = $orders;
        $revenue = $orders;
        $paidRevenue = $orders;

        foreach (
            Connection::get()->select_query($sql, ...$parameters)
            as $row
        ) {
            $bucket = self::number($row['bucket']);

            $orders[$bucket] = self::number($row['orders']);
            $paid[$bucket] = self::number($row['paid']);
            $revenue[$bucket] = self::number($row['revenue']);
            $paidRevenue[$bucket] = self::number($row['paid_revenue']);
        }

        return new OrderFigures($orders, $paid, $revenue, $paidRevenue);
    }

    /**
     * The query {@see self::figures()} runs, and its parameters.
     *
     * @return array{0: string, 1: array<int, string|int>}
     */
    public function query(Period $period, bool $comparison = false): array
    {
        $bounds = $period->bounds($comparison);
        [$from, $to] = $period->span($comparison);
        $bucketParameters = Period::sql_bucket('`created`', $bounds);
        $bucket = (string) array_shift($bucketParameters);

        $where = '`state` IN (?, ?) AND `created` >= ? AND `created` < ?';
        // A row from before the state column holds '', and was a real order.
        $parameters = [OrderState::Placed->value, '', $from, $to];

        if ($this->scope !== null) {
            $scopeParameters = ($this->scope)();
            $where .= ' AND (' . array_shift($scopeParameters) . ')';
            $parameters = [...$parameters, ...$scopeParameters];
        }

        $isPaid = '`payment_status` = ?';
        $paid = PaymentStatus::Paid->value;

        return [
            'SELECT ' .
            $bucket .
            ' AS `bucket`,
                COUNT(*) AS `orders`,
                COALESCE(SUM(' .
            $isPaid .
            '), 0) AS `paid`,
                COALESCE(SUM(`total`), 0) AS `revenue`,
                COALESCE(SUM(CASE WHEN ' .
            $isPaid .
            ' THEN `total` ELSE 0 END), 0) AS `paid_revenue`
            FROM `' .
            Order::TABLE .
            '`
            WHERE ' .
            $where .
            '
            GROUP BY `bucket`',
            // In the order the placeholders appear: the bucket, the two paid
            // tests, then the WHERE clause.
            [...$bucketParameters, $paid, $paid, ...$parameters],
        ];
    }

    /**
     * A figure as the driver hands it back — a SUM is a numeric string.
     */
    private static function number(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }
}
