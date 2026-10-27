<?php

declare(strict_types=1);

namespace Tnt\Ecommerce\Model;

use dry\orm\Model;
use Tnt\Ecommerce\Payment\EntryKind;
use Tnt\Ecommerce\Payment\PaymentStatus;

/**
 * One line of the payment ledger, `ecommerce_payment_entry`. Append-only:
 * written by {@see \Tnt\Ecommerce\Payment\PaymentLedger}, never updated or
 * deleted. See docs/payment.md.
 *
 * @property int|null $id
 * @property int $created
 * @property Order|null $order
 * @property string $provider
 * @property string $payment_id
 * @property string $kind
 * @property string|null $status
 * @property int|null $amount
 * @property string|null $reference
 * @property string|null $method
 * @property string|null $card_label
 */
class PaymentEntry extends Model
{
    const TABLE = 'ecommerce_payment_entry';

    /**
     * @var array<string, string>
     */
    public static $special_fields = [
        'order' => Order::class,
    ];

    /**
     * @return EntryKind
     */
    public function getKind(): EntryKind
    {
        return EntryKind::from($this->kind);
    }

    /**
     * The reported status on a `status_reported` entry, else null.
     *
     * @return PaymentStatus|null
     */
    public function getStatus(): ?PaymentStatus
    {
        return PaymentStatus::tryFrom((string) $this->status);
    }

    /**
     * Cents; 0 on an entry that moves no money.
     *
     * @return int
     */
    public function getAmount(): int
    {
        return (int) $this->amount;
    }

    /**
     * How the money was paid, as the provider names it (`bancontact`), on a
     * `captured` entry whose report said so; else null — every entry
     * written before the column existed included.
     *
     * @return string|null
     */
    public function getMethod(): ?string
    {
        return self::word($this->method);
    }

    /**
     * The card's brand ("Mastercard") on a `captured` entry whose report
     * named one; else null.
     *
     * @return string|null
     */
    public function getCardLabel(): ?string
    {
        return self::word($this->card_label);
    }

    /**
     * @param string|null $value
     * @return string|null
     */
    private static function word(?string $value): ?string
    {
        return $value === null || $value === '' ? null : $value;
    }
}
