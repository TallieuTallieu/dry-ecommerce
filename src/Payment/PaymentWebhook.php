<?php

declare(strict_types=1);

namespace Tnt\Ecommerce\Payment;

use Tnt\Ecommerce\Contracts\PaymentGatewayInterface;
use Tnt\Ecommerce\Model\Order;
use Tnt\Ecommerce\Repository\PaymentEntryRepository;
use Tnt\Ecommerce\UnknownPayment;

/**
 * The package's half of a payment webhook: finds the order through the
 * ledger's attempt for the posted payment id, asks the gateway for its
 * report, and hands it to {@see PaymentLedger}. Any attempt the shop ever
 * started resolves, not only the current one. The route itself is the
 * project's to register. See docs/payment.md.
 */
class PaymentWebhook
{
    /**
     * @var PaymentGatewayInterface
     */
    private PaymentGatewayInterface $gateway;

    /**
     * @var PaymentLedger
     */
    private PaymentLedger $ledger;

    /**
     * @param PaymentGatewayInterface $gateway The configured gateway — only
     *        bound under this name when `ecommerce.payment` names a class
     *        that implements it, so a shop on a synchronous gateway fails
     *        to resolve this handler rather than half-working.
     * @param PaymentLedger $ledger
     */
    public function __construct(
        PaymentGatewayInterface $gateway,
        PaymentLedger $ledger
    ) {
        $this->gateway = $gateway;
        $this->ledger = $ledger;
    }

    /**
     * Handle one webhook call. Deliberately not answering the HTTP request —
     * what a provider expects back is the project's route's business.
     *
     * @param string $paymentId The id the provider posted.
     * @return void
     *
     * @throws UnknownPayment If no attempt carries the id — after writing
     *                        one `unknown_payment` entry about it.
     */
    public function handle(string $paymentId): void
    {
        $provider = $this->gateway->provider();
        $order = $this->findOrder($provider, $paymentId);

        if ($order === null) {
            $this->ledger->unknown($provider, $paymentId);

            throw UnknownPayment::id($paymentId);
        }

        $this->ledger->apply(
            $order,
            $provider,
            $this->gateway->reportOf($paymentId)
        );
    }

    /**
     * The order whose attempt carries a payment id. A protected seam, like
     * `Cart::newOrder()`: a test overrides it to answer from memory.
     *
     * @param string $provider
     * @param string $paymentId
     * @return Order|null
     */
    protected function findOrder(string $provider, string $paymentId): ?Order
    {
        return PaymentEntryRepository::create()
            ->forPayment($provider, $paymentId)
            ->onOrders()
            ->firstOrNull()?->order;
    }
}
