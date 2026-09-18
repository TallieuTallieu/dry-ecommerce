<?php

declare(strict_types=1);

namespace Tnt\Ecommerce\Payment;

/**
 * The provider would not create the payment. The id, when the provider gave
 * one, is recorded; the order ends up failed and re-placeable.
 */
final class PaymentRefused implements PaymentOutcome
{
    /**
     * @param string|null $paymentId
     */
    public function __construct(public readonly ?string $paymentId = null) {}
}
