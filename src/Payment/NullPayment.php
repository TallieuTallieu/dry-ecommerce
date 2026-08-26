<?php

namespace Tnt\Ecommerce\Payment;

use Oak\Contracts\Dispatcher\DispatcherInterface;
use Tnt\Ecommerce\Contracts\OrderInterface;
use Tnt\Ecommerce\Contracts\PaymentInterface;
use Tnt\Ecommerce\Events\Order\Paid;

/**
 * The shipped dummy gateway: it charges nobody and reports success — a live
 * risk in a shop that never got round to payment; set a real gateway before
 * launch. Also the reference shape: dispatch the event that says what the
 * money did, and let the listeners write `payment_status`. See docs/payment.md.
 */
class NullPayment implements PaymentInterface
{
    /**
     * @var DispatcherInterface $dispatcher
     */
    private $dispatcher;

    /**
     * NullPayment constructor.
     * @param DispatcherInterface $dispatcher
     */
    public function __construct(DispatcherInterface $dispatcher)
    {
        $this->dispatcher = $dispatcher;
    }

    /**
     * "Take" the payment: succeed on the spot, synchronously.
     *
     * @param OrderInterface $order
     * @return mixed|void
     */
    public function pay(OrderInterface $order)
    {
        // Payment complete
        $this->dispatcher->dispatch(Paid::class, new Paid($order));
    }
}
