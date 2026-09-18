<?php

declare(strict_types=1);

namespace Tnt\Ecommerce\Payment;

use InvalidArgumentException;

/**
 * One money movement as a provider reports it: its kind, the provider's own
 * id for it, and the amount in cents. See docs/payment.md.
 */
final class Movement
{
    /**
     * @param EntryKind $kind A kind that moves money.
     * @param string $reference The provider's id for this movement — what
     *                          makes a replayed report write nothing.
     * @param int $amount Cents, zero or more; the kind says which way.
     *
     * @throws InvalidArgumentException For a kind that moves no money, or a
     *                                  negative amount.
     */
    public function __construct(
        public readonly EntryKind $kind,
        public readonly string $reference,
        public readonly int $amount
    ) {
        if (!$kind->movesMoney()) {
            throw new InvalidArgumentException(
                sprintf("'%s' is not a money movement.", $kind->value)
            );
        }

        if ($amount < 0) {
            throw new InvalidArgumentException(
                sprintf(
                    'A movement amount is zero or more cents, %d given; ' .
                        'the kind says which way the money went.',
                    $amount
                )
            );
        }
    }
}
