<?php

declare(strict_types=1);

namespace Tnt\Ecommerce\Payment;

/**
 * What one payment ledger entry records. Five kinds move money, the other
 * three record what happened around it. See docs/payment.md.
 */
enum EntryKind: string
{
    /**
     * An attempt to pay began — the first entry under a payment id.
     */
    case AttemptStarted = 'attempt_started';

    /**
     * The provider reported a new status for the attempt.
     */
    case StatusReported = 'status_reported';

    /**
     * Money arrived.
     */
    case Captured = 'captured';

    /**
     * Money went back to the customer.
     */
    case Refunded = 'refunded';

    /**
     * A refund was undone.
     */
    case RefundReversed = 'refund_reversed';

    /**
     * The customer's bank took the money back.
     */
    case Chargeback = 'chargeback';

    /**
     * A chargeback was undone.
     */
    case ChargebackReversed = 'chargeback_reversed';

    /**
     * A webhook named a payment id the shop never issued.
     */
    case UnknownPayment = 'unknown_payment';

    /**
     * Whether an entry of this kind carries an amount.
     *
     * @return bool
     */
    public function movesMoney(): bool
    {
        return match ($this) {
            self::Captured,
            self::Refunded,
            self::RefundReversed,
            self::Chargeback,
            self::ChargebackReversed
                => true,
            default => false,
        };
    }

    /**
     * The kind a reversal undoes, or null for anything that is not one.
     *
     * @return EntryKind|null
     */
    public function reverses(): ?EntryKind
    {
        return match ($this) {
            self::RefundReversed => self::Refunded,
            self::ChargebackReversed => self::Chargeback,
            default => null,
        };
    }
}
