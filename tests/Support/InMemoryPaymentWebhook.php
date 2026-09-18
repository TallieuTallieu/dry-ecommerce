<?php

declare(strict_types=1);

namespace Tests\Support;

use Tnt\Ecommerce\Contracts\PaymentGatewayInterface;
use Tnt\Ecommerce\Model\Order;
use Tnt\Ecommerce\Payment\PaymentWebhook;

/**
 * The production webhook handler over the findOrder() seam: the lookup that
 * would query `ecommerce_payment_entry` reads the in-memory ledger's entries
 * instead, and everything else in handle() runs for real.
 */
final class InMemoryPaymentWebhook extends PaymentWebhook
{
    /**
     * @var InMemoryPaymentLedger
     */
    private InMemoryPaymentLedger $entries;

    /**
     * @param PaymentGatewayInterface $gateway
     * @param InMemoryPaymentLedger $ledger
     */
    public function __construct(
        PaymentGatewayInterface $gateway,
        InMemoryPaymentLedger $ledger
    ) {
        parent::__construct($gateway, $ledger);

        $this->entries = $ledger;
    }

    /**
     * The same question the repository asks: the first entry under this
     * provider and payment id that belongs to an order.
     *
     * @param string $provider
     * @param string $paymentId
     * @return Order|null
     */
    protected function findOrder(string $provider, string $paymentId): ?Order
    {
        foreach ($this->entries->written as $entry) {
            if (
                $entry->provider === $provider &&
                $entry->payment_id === $paymentId &&
                $entry->order !== null
            ) {
                return $entry->order;
            }
        }

        return null;
    }
}
