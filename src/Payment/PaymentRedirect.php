<?php

declare(strict_types=1);

namespace Tnt\Ecommerce\Payment;

/**
 * The provider created a payment and the visitor must go pay it.
 */
final class PaymentRedirect implements PaymentOutcome
{
    /**
     * @param string $paymentId
     * @param string $checkoutUrl
     */
    public function __construct(
        public readonly string $paymentId,
        public readonly string $checkoutUrl
    ) {}
}
