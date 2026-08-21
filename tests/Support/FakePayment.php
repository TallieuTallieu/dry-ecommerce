<?php

declare(strict_types=1);

namespace Tests\Support;

use Tnt\Ecommerce\Contracts\OrderInterface;
use Tnt\Ecommerce\Contracts\PaymentInterface;

/**
 * A payment method that records what it was asked to pay and does nothing.
 *
 * The cart takes a `PaymentInterface` in its constructor, so building one in a
 * test needs an implementation. `NullPayment` would do, but it wants a
 * dispatcher.
 */
final class FakePayment implements PaymentInterface
{
    /**
     * @var array<int, OrderInterface>
     */
    public array $paid = [];

    /**
     * @param OrderInterface $order
     * @return void
     */
    public function pay(OrderInterface $order)
    {
        $this->paid[] = $order;
    }
}
