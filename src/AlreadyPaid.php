<?php

declare(strict_types=1);

namespace Tnt\Ecommerce;

use LogicException;
use Tnt\Ecommerce\Model\Order;

/**
 * Placing an order whose money already arrived is refused: re-placement
 * re-freezes the same row from the cart, and that would rewrite what was
 * paid for. See docs/orders.md.
 */
final class AlreadyPaid extends LogicException
{
    private function __construct(string $message)
    {
        parent::__construct($message);
    }

    /**
     * @param Order $order The order that was refused.
     * @return self
     */
    public static function order(Order $order): self
    {
        $reference = (string) $order->order_id;

        return new self(
            sprintf(
                'Order %s cannot be placed again: its payment status is ' .
                    "'%s'. Re-placement re-freezes the order from the cart, " .
                    'and money has already arrived for what it froze last ' .
                    'time. A correction to a paid order is a refund and a ' .
                    'new order, not a rewrite.',
                $reference !== '' ? $reference : '#' . ($order->id ?? '?'),
                $order->getPaymentStatus()->value
            )
        );
    }
}
