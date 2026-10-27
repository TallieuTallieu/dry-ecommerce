<?php

declare(strict_types=1);

namespace Tnt\Ecommerce\Payment;

/**
 * What a provider says about one payment now: its status, every money
 * movement it knows of, and how it was paid. The gateway reports; the ledger
 * writes what is new. See docs/payment.md.
 */
final class PaymentReport
{
    /**
     * @param string $paymentId
     * @param PaymentStatus $status
     * @param list<Movement> $movements Every movement, not only new ones.
     * @param string|null $method The provider's id for how the payment was
     *                            paid (`bancontact`, `creditcard`), or null
     *                            when it does not say. Kept on the capture.
     * @param string|null $cardLabel The card's brand ("Mastercard") when the
     *                               provider names one, else null. Kept on
     *                               the capture.
     */
    public function __construct(
        public readonly string $paymentId,
        public readonly PaymentStatus $status,
        public readonly array $movements = [],
        public readonly ?string $method = null,
        public readonly ?string $cardLabel = null
    ) {}
}
