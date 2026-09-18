<?php

declare(strict_types=1);

namespace Tnt\Ecommerce\Repository;

use Tnt\Dbi\Criteria\Equals;
use Tnt\Dbi\Criteria\NotNull;
use Tnt\Dbi\Criteria\OrderBy;
use Tnt\Ecommerce\Model\Order;
use Tnt\Ecommerce\Model\PaymentEntry;

/**
 * Reads `ecommerce_payment_entry`, oldest entry first — the ledger's order
 * is the order things happened in.
 *
 * @extends Repository<PaymentEntry>
 */
class PaymentEntryRepository extends Repository
{
    protected string $model = PaymentEntry::class;

    protected function init(): void
    {
        $this->addCriteria(new OrderBy('id'));
    }

    /**
     * One order's payment history, across every attempt.
     *
     * @param Order $order
     * @return static
     */
    public function forOrder(Order $order): static
    {
        $this->addCriteria(new Equals('order', $order->id));

        return $this;
    }

    /**
     * One attempt's entries, as the provider knows it.
     *
     * @param string $provider
     * @param string $paymentId
     * @return static
     */
    public function forPayment(string $provider, string $paymentId): static
    {
        $this->addCriteria(new Equals('provider', $provider));
        $this->addCriteria(new Equals('payment_id', $paymentId));

        return $this;
    }

    /**
     * Only entries that belong to an order — leaves out `unknown_payment`.
     *
     * @return static
     */
    public function onOrders(): static
    {
        $this->addCriteria(new NotNull('order'));

        return $this;
    }
}
