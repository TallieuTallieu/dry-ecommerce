<?php

declare(strict_types=1);

namespace Tests\Support;

use Tnt\Ecommerce\Model\Order;
use Tnt\Ecommerce\Model\PaymentAttempt;
use Tnt\Ecommerce\Payment\PaymentWebhook;

/**
 * The production webhook handler over its two lookup seams: what would query
 * `ecommerce_payment_attempt` and `ecommerce_order.payment_id` answers from
 * these maps instead, and everything else in handle() runs for real.
 */
final class InMemoryPaymentWebhook extends PaymentWebhook
{
    /**
     * The orders the "database" holds, keyed by payment id.
     *
     * @var array<string, Order>
     */
    public array $orders = [];

    /**
     * @param string $paymentId
     * @return Order|null
     */
    protected function findOrder(string $paymentId): ?Order
    {
        return $this->orders[$paymentId] ?? null;
    }

    /**
     * The attempts the "database" holds, keyed by payment id. Empty by
     * default, which is a gateway that writes `payment_id` by hand and never
     * records an attempt — the fallback path handle() still has to serve.
     *
     * @var array<string, PaymentAttempt>
     */
    public array $attempts = [];

    /**
     * @param string $paymentId
     * @return PaymentAttempt|null
     */
    protected function findAttempt(string $paymentId): ?PaymentAttempt
    {
        return $this->attempts[$paymentId] ?? null;
    }
}
