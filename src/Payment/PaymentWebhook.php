<?php

declare(strict_types=1);

namespace Tnt\Ecommerce\Payment;

use Oak\Contracts\Dispatcher\DispatcherInterface;
use Tnt\Ecommerce\Contracts\PaymentGatewayInterface;
use Tnt\Ecommerce\Events\Order\Paid;
use Tnt\Ecommerce\Events\Order\PaymentCanceled;
use Tnt\Ecommerce\Events\Order\PaymentExpired;
use Tnt\Ecommerce\Events\Order\PaymentFailed;
use Tnt\Ecommerce\Events\Order\PaymentPartiallyRefunded;
use Tnt\Ecommerce\Events\Order\PaymentRefunded;
use Tnt\Ecommerce\Model\Order;
use Tnt\Ecommerce\Model\PaymentAttempt;
use Tnt\Ecommerce\Repository\OrderRepository;
use Tnt\Ecommerce\Repository\PaymentAttemptRepository;
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
        // The attempt first: a re-placed order no longer carries its old id,
        // and the attempt row is what still answers for it. The order's own
        // column is the fallback, for a gateway that writes `payment_id` by
        // hand rather than through Order::startPaymentAttempt().
        $attempt = $this->findAttempt($paymentId);
        $order =
            $attempt === null ? $this->findOrder($paymentId) : $attempt->order;

        if ($order === null) {
            throw UnknownPayment::id($paymentId);
        }

        $status = $this->gateway->statusOf($paymentId);

        // Every attempt keeps its own record, live or superseded — that is
        // what makes "which attempt was this webhook about" answerable.
        $attempt?->setStatus($status);

        // Only the attempt the order is waiting on may move the order. News
        // about a superseded attempt is recorded above and goes no further:
        // the order has since been re-placed and is owed a different payment.
        if ($attempt !== null && (string) $order->payment_id !== $paymentId) {
            return;
        }

        $event = match ($status) {
            PaymentStatus::Pending => null,
            PaymentStatus::Paid => Paid::class,
            PaymentStatus::Failed => PaymentFailed::class,
            PaymentStatus::Canceled => PaymentCanceled::class,
            PaymentStatus::Expired => PaymentExpired::class,
            PaymentStatus::Refunded => PaymentRefunded::class,
            PaymentStatus::PartiallyRefunded
                => PaymentPartiallyRefunded::class,
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

    /**
     * The attempt a payment id names, or null when the gateway never
     * recorded one. A protected seam, like {@see findOrder()}.
     *
     * @param string $paymentId
     * @return PaymentAttempt|null
     */
    protected function findAttempt(string $paymentId): ?PaymentAttempt
    {
        return PaymentAttemptRepository::create()
            ->byPaymentId($paymentId)
            ->firstOrNull();
    }
}
