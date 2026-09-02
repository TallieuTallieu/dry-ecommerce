<?php

declare(strict_types=1);

namespace Tnt\Ecommerce\Payment;

use Oak\Contracts\Dispatcher\DispatcherInterface;
use Tnt\Ecommerce\Contracts\PaymentGatewayInterface;
use Tnt\Ecommerce\Events\Order\Paid;
use Tnt\Ecommerce\Events\Order\PaymentCanceled;
use Tnt\Ecommerce\Events\Order\PaymentExpired;
use Tnt\Ecommerce\Events\Order\PaymentFailed;
use Tnt\Ecommerce\Events\Order\PaymentRefunded;
use Tnt\Ecommerce\Model\Order;
use Tnt\Ecommerce\Repository\OrderRepository;
use Tnt\Ecommerce\UnknownPayment;

/**
 * The package's half of a payment webhook: finds the order the posted
 * payment id belongs to, asks the gateway what its provider says happened,
 * and dispatches the matching event. The listeners write the column, and
 * their transition guard is what makes replays and late arrivals harmless.
 * The route itself is the project's to register. See docs/payment.md.
 */
class PaymentWebhook
{
    /**
     * @var PaymentGatewayInterface
     */
    private PaymentGatewayInterface $gateway;

    /**
     * @var DispatcherInterface
     */
    private DispatcherInterface $dispatcher;

    /**
     * @param PaymentGatewayInterface $gateway The configured gateway — only
     *        bound under this name when `ecommerce.payment` names a class
     *        that implements it, so a shop on a synchronous gateway fails
     *        to resolve this handler rather than half-working.
     * @param DispatcherInterface $dispatcher
     */
    public function __construct(
        PaymentGatewayInterface $gateway,
        DispatcherInterface $dispatcher
    ) {
        $this->gateway = $gateway;
        $this->dispatcher = $dispatcher;
    }

    /**
     * Handle one webhook call: dispatch the event the provider's current
     * status maps to, or nothing while the provider still says pending.
     * Deliberately not answering the HTTP request — what a provider expects
     * back is the project's route's business.
     *
     * @param string $paymentId The id the provider posted.
     * @return void
     *
     * @throws UnknownPayment If no order carries the id.
     */
    public function handle(string $paymentId): void
    {
        $order = $this->findOrder($paymentId);

        if ($order === null) {
            throw UnknownPayment::id($paymentId);
        }

        $event = match ($this->gateway->statusOf($paymentId)) {
            PaymentStatus::Pending => null,
            PaymentStatus::Paid => Paid::class,
            PaymentStatus::Failed => PaymentFailed::class,
            PaymentStatus::Canceled => PaymentCanceled::class,
            PaymentStatus::Expired => PaymentExpired::class,
            PaymentStatus::Refunded => PaymentRefunded::class,
        };

        if ($event === null) {
            return;
        }

        $this->dispatcher->dispatch($event, new $event($order));
    }

    /**
     * The order a payment id belongs to. A protected seam, like
     * `Cart::newOrder()`: a test overrides it to answer from memory and the
     * whole of handle() runs with no database.
     *
     * @param string $paymentId
     * @return Order|null
     */
    protected function findOrder(string $paymentId): ?Order
    {
        return OrderRepository::create()
            ->byPaymentId($paymentId)
            ->firstOrNull();
    }
}
