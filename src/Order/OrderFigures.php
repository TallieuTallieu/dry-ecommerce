<?php

declare(strict_types=1);

namespace Tnt\Ecommerce\Order;

/**
 * What {@see OrderStats} counted over a period, per bucket of it: one value
 * per bucket in each list, in the period's order, a bucket without orders 0.
 * Money is integer cents.
 */
final class OrderFigures
{
    /**
     * @param array<int, int> $orders Orders placed
     * @param array<int, int> $paid Of those, the ones paid
     * @param array<int, int> $revenue What the orders placed came to
     * @param array<int, int> $paidRevenue What the paid ones came to
     */
    public function __construct(
        public readonly array $orders,
        public readonly array $paid,
        public readonly array $revenue,
        public readonly array $paidRevenue
    ) {}

    /**
     * The whole period's figures, the buckets added up.
     *
     * @return array{orders: int, paid: int, revenue: int, paid_revenue: int}
     */
    public function totals(): array
    {
        return [
            'orders' => array_sum($this->orders),
            'paid' => array_sum($this->paid),
            'revenue' => array_sum($this->revenue),
            'paid_revenue' => array_sum($this->paidRevenue),
        ];
    }
}
