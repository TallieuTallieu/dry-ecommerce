<?php

declare(strict_types=1);

namespace Tests\Support;

use LogicException;
use Tnt\Ecommerce\Model\Order;
use Tnt\Ecommerce\Model\PaymentEntry;
use Tnt\Ecommerce\Payment\PaymentLedger;

/**
 * The production ledger over its two writes: entries land in memory, and on
 * the order when it keeps its own ({@see KeepsPaymentEntries}). Holds the
 * table's UNIQUE (`provider`, `kind`, `reference`) too, so a duplicate write
 * fails here the way it would in MySQL. `payment_status` is kept per order
 * row, so two orders with one id are two requests on the same row.
 */
final class InMemoryPaymentLedger extends PaymentLedger
{
    /**
     * Every entry written, in order — `unknown_payment` ones included.
     *
     * @var list<PaymentEntry>
     */
    public array $written = [];

    /**
     * `payment_status` as the table holds it, per order row. A row this
     * ledger has not moved yet holds what its first mover read.
     *
     * @var array<int|string, string|null>
     */
    public array $statuses = [];

    /**
     * @param PaymentEntry $entry
     * @return void
     */
    protected function write(PaymentEntry $entry): void
    {
        if ($entry->reference !== null) {
            foreach ($this->written as $existing) {
                if (
                    $existing->provider === $entry->provider &&
                    $existing->kind === $entry->kind &&
                    $existing->reference === $entry->reference
                ) {
                    throw new LogicException(
                        'Duplicate entry for UNIQUE (provider, kind, reference).'
                    );
                }
            }
        }

        $entry->id = count($this->written) + 1;
        $this->written[] = $entry;

        $order = $entry->order;

        if ($order !== null && method_exists($order, 'keepPaymentEntry')) {
            $order->keepPaymentEntry($entry);
        }
    }

    /**
     * @param Order $order
     * @param string|null $from
     * @param string $to
     * @return bool
     */
    protected function moveStatus(Order $order, ?string $from, string $to): bool
    {
        $row = $order->id ?? 'object ' . spl_object_id($order);

        if (
            array_key_exists($row, $this->statuses) &&
            $this->statuses[$row] !== $from
        ) {
            return false;
        }

        $this->statuses[$row] = $to;

        return true;
    }

    /**
     * The kinds written so far, in order — the shape most tests assert.
     *
     * @return list<string>
     */
    public function kinds(): array
    {
        return array_map(
            static fn(PaymentEntry $entry): string => $entry->kind,
            $this->written
        );
    }
}
