<?php

declare(strict_types=1);

namespace Tnt\Ecommerce\Repository;

use Tnt\Dbi\Criteria\Equals;
use Tnt\Dbi\Criteria\OrderBy;
use Tnt\Ecommerce\Model\Order;
use Tnt\Ecommerce\Model\PaymentAttempt;

/**
 * Reads `ecommerce_payment_attempt`. {@see byPaymentId()} is the webhook
 * lookup — the one that makes a superseded attempt still answerable instead
 * of a 404 on the provider's full retry schedule.
 *
 * @extends Repository<PaymentAttempt>
 */
class PaymentAttemptRepository extends Repository
{
    protected string $model = PaymentAttempt::class;

    /**
     * Newest first: a shop asking an order for its attempts wants the live
     * one at the top.
     */
    protected function init(): void
    {
        $this->addCriteria(new OrderBy('created', 'DESC'));
    }

    /**
     * Filter to the attempt a provider's id names.
     *
     * @param string $paymentId
     * @return static
     */
    public function byPaymentId(string $paymentId): static
    {
        $this->addCriteria(new Equals('payment_id', $paymentId));

        return $this;
    }

    /**
     * Filter to the attempts made on one order.
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
