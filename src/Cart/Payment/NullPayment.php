<?php

namespace Tnt\Ecommerce\Payment;

use Oak\Contracts\Dispatcher\DispatcherInterface;
use Tnt\Ecommerce\Contracts\OrderInterface;
use Tnt\Ecommerce\Contracts\PaymentInterface;
use Tnt\Ecommerce\Events\Order\Paid;

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
     * @param OrderInterface $order
     * @return mixed|void
     */
    public function pay(OrderInterface $order)
    {
        // Payment complete
        $this->dispatcher->dispatch(Paid::class, new Paid($order));
    }
}