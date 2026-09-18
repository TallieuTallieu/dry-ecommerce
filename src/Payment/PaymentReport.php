<?php

declare(strict_types=1);

namespace Tnt\Ecommerce\Payment;

/**
 * What a provider says about one payment now: its status and every money
 * movement it knows of. The gateway reports; the ledger writes what is new.
 * See docs/payment.md.
 */
final class PaymentReport
{
    /**
     * @param string $paymentId
     * @param PaymentStatus $status
     * @param list<Movement> $movements Every movement, not only new ones.
     */
    public function __construct(
        public readonly string $paymentId,
        public readonly PaymentStatus $status,
        public readonly array $movements = []
    ) {}
}
