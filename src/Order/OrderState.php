<?php

declare(strict_types=1);

namespace Tnt\Ecommerce\Order;

/**
 * Where an order stands in its own lifecycle — draft or placed. This is not
 * the payment's state ({@see \Tnt\Ecommerce\Payment\PaymentStatus}): "fixed"
 * is placed *and* paid, as a query, never a third case. See docs/orders.md.
 */
enum OrderState: string
{
    /**
     * Being filled in progressively; no lines, no reference, no events yet.
     * Invisible to {@see \Tnt\Ecommerce\Repository\OrderRepository::placed()}.
     */
    case Draft = 'draft';

    /**
     * Frozen from the cart: lines copied, money frozen, `Created` dispatched.
     */
    case Placed = 'placed';
}
