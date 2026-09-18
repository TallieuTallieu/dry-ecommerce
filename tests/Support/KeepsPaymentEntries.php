<?php

declare(strict_types=1);

namespace Tests\Support;

use Tnt\Ecommerce\Model\PaymentEntry;

/**
 * An order's payment history kept on the instance instead of read from
 * `ecommerce_payment_entry` — what {@see InMemoryPaymentLedger} writes into.
 */
trait KeepsPaymentEntries
{
    /**
     * @var list<PaymentEntry>
     */
    public array $entries = [];

    /**
     * @return list<PaymentEntry>
     */
    public function getPaymentEntries(): array
    {
        return $this->entries;
    }

    /**
     * @param PaymentEntry $entry
     * @return void
     */
    public function keepPaymentEntry(PaymentEntry $entry): void
    {
        $this->entries[] = $entry;
    }
}
