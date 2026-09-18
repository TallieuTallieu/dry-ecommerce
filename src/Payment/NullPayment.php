<?php

namespace Tnt\Ecommerce\Payment;

use Tnt\Ecommerce\Contracts\OrderInterface;
use Tnt\Ecommerce\Contracts\PaymentInterface;

/**
 * The shipped dummy gateway: it charges nobody and reports the order total
 * captured — a live risk in a shop that never got round to payment; set a
 * real gateway before launch. See docs/payment.md.
 */
class NullPayment implements PaymentInterface
{
    /**
     * @return string
     */
    public function provider(): string
    {
        return 'null';
    }

    /**
     * "Take" the payment: settled on the spot, under an id minted per call
     * so a re-placement is a new attempt with a capture of its own.
     *
     * @param OrderInterface $order
     * @return PaymentOutcome
     *
     * @throws \Random\RandomException If the system has no secure randomness.
     */
    public function pay(OrderInterface $order): PaymentOutcome
    {
        $paymentId = 'null_' . bin2hex(random_bytes(8));

        return new PaymentSettled(
            new PaymentReport($paymentId, PaymentStatus::Paid, [
                new Movement(
                    EntryKind::Captured,
                    $paymentId,
                    $order->getTotal()
                ),
            ])
        );
    }
}
