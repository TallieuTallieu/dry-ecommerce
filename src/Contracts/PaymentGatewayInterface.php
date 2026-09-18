<?php

declare(strict_types=1);

namespace Tnt\Ecommerce\Contracts;

use Tnt\Ecommerce\Payment\PaymentReport;

/**
 * The opt-in shape for a real, asynchronous gateway: pay() as inherited, plus
 * the webhook half — asked about a payment id, the gateway interrogates its
 * provider's API and reports. The package's
 * {@see \Tnt\Ecommerce\Payment\PaymentWebhook} does the rest. See
 * docs/payment.md.
 */
interface PaymentGatewayInterface extends PaymentInterface
{
    /**
     * What the provider says about this payment now: its status and every
     * money movement it knows of, each under the provider's own id.
     *
     * Report everything, every time — the ledger writes only what is new.
     * Ask the provider's API, never the webhook body.
     *
     * @param string $paymentId
     * @return PaymentReport
     */
    public function reportOf(string $paymentId): PaymentReport;
}
