<?php

namespace Tnt\Ecommerce\Repository;

use Tnt\Dbi\Criteria\Equals;
use Tnt\Dbi\Criteria\OrderBy;
use Tnt\Ecommerce\Model\Order;
use Tnt\Ecommerce\Model\OrderItem;

/**
 * Reads `ecommerce_order_item`.
 *
 * Order lines are a frozen copy of the cart lines at checkout, so the only
 * question ever asked of them is "which lines belong to this order", in the
 * order they were added.
 *
 * @extends Repository<OrderItem>
 */
class OrderItemRepository extends Repository
{
    protected string $model = OrderItem::class;

    protected function init(): void
    {
        $this->addCriteria(new OrderBy('id'));
    }

    /**
     * Filter to the lines of one order.
     *
     * @param Order $order
     * @return static
     */
    public function forOrder(Order $order): static
    {
        $this->addCriteria(new Equals('order', $order->id));

        return $this;
    }
}
