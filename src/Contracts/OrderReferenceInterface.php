<?php

declare(strict_types=1);

namespace Tnt\Ecommerce\Contracts;

use Tnt\Ecommerce\Model\Order;

/**
 * Makes an order's `order_id`: the reference a customer quotes, printed on
 * every mail, document and payment. Bound from `ecommerce.order_reference`,
 * {@see \Tnt\Ecommerce\Order\RandomOrderReference} when unset;
 * {@see \Tnt\Ecommerce\Order\YearlyOrderReference} ships beside it. See
 * docs/orders.md#the-order-reference.
 */
interface OrderReferenceInterface
{
    /**
     * Called once per order, at its first placement, after the row has been
     * written — so the order has its id. A re-placed order keeps what this
     * answered the first time. The answer must be unique: `order_id` carries
     * no UNIQUE index, so nothing else will catch a repeat.
     *
     * @param Order $order
     * @return string
     */
    public function reference(Order $order): string;
}
