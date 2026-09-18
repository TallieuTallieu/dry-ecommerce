<?php

namespace Tnt\Ecommerce\Contracts;

use Tnt\Ecommerce\Payment\PaymentOutcome;

/**
 * A payment gateway. pay() says what it did; the package records it in the
 * ledger and does the redirect. See docs/payment.md.
 */
interface PaymentInterface
{
    /**
     * The name every ledger entry is filed under — stable, lowercase, e.g.
     * `mollie`. Changing it orphans the entries already written.
     *
     * @return string
     */
    public function provider(): string;

    /**
     * Start paying for a placed order, and answer what happened. Never
     * writes the order and never redirects — the package does both.
     *
     * @param OrderInterface $order
     * @return PaymentOutcome
     */
    public function pay(OrderInterface $order): PaymentOutcome;
}
