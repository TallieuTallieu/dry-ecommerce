<?php

declare(strict_types=1);

namespace Tnt\Ecommerce\Contracts;

use Tnt\Ecommerce\Payment\PaymentStatus;

/**
 * The opt-in shape for a real, asynchronous gateway: pay() as inherited, plus
 * the webhook half — asked about a payment id, the gateway interrogates its
 * provider's API and answers where the money stands. The package's
 * {@see \Tnt\Ecommerce\Payment\PaymentWebhook} does the rest. See
 * docs/payment.md.
 */
interface PaymentGatewayInterface extends PaymentInterface
{
    /**
     * Where the money for this payment stands, according to the provider.
     *
     * Called by the webhook handler when the provider announces a change.
     * Answer with the status the provider's API reports now — not what the
     * webhook body claims — and answer {@see PaymentStatus::Pending} when
     * nothing has been decided yet: pending is the one answer that dispatches
     * no event.
     *
     * @param string $paymentId The provider's own id, as stored on
     *                          `ecommerce_order.payment_id` by pay().
     * @return PaymentStatus
     */
    public function statusOf(string $paymentId): PaymentStatus;

    /**
     * Where to send a visitor to finish a payment they walked away from, or
     * null when the provider has no page left to offer — it expired, it was
     * canceled, it is already paid.
     *
     * Without this a shop that wants to offer "continue where you left off"
     * has to reach past this interface to the concrete provider's SDK, and
     * the gateway stops being swappable. Take the id from
     * {@see \Tnt\Ecommerce\Contracts\OrderInterface::getPaymentId()}.
     *
     * @param string $paymentId The provider's own id.
     * @return string|null
     */
    public function resumeUrl(string $paymentId): ?string;
}
