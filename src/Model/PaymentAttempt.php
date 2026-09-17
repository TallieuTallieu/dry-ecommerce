<?php

declare(strict_types=1);

namespace Tnt\Ecommerce\Model;

use dry\orm\Model;
use Tnt\Ecommerce\Payment\PaymentStatus;

/**
 * One go at paying an order, as stored in `ecommerce_payment_attempt`.
 *
 * An order is re-placeable, so it can have several of these; the one whose
 * `payment_id` matches `ecommerce_order.payment_id` is the live one, and the
 * rest are history a webhook can still be answered from. See docs/payment.md.
 *
 * @property int|null $id
 * @property int $created
 * @property int $updated
 * @property Order|null $order
 * @property string $payment_id
 * @property string|null $status
 * @property string|null $payment_key
 */
class PaymentAttempt extends Model
{
    const TABLE = 'ecommerce_payment_attempt';

    /**
     * @var array<string, string>
     */
    public static $special_fields = [
        'order' => Order::class,
    ];

    /**
     * Where this attempt got to. A column this class cannot read — legacy
     * `''`, or an unknown word — reads as {@see PaymentStatus::Pending}, the
     * one status that claims nothing, exactly as on the order.
     *
     * @return PaymentStatus
     */
    public function getStatus(): PaymentStatus
    {
        return PaymentStatus::tryFrom((string) $this->status) ??
            PaymentStatus::Pending;
    }

    /**
     * Record where this attempt got to, and save.
     *
     * Through the same transition guard the order uses: a provider's webhooks
     * arrive at least once and out of order, and an attempt that has been
     * paid must not be talked back down by a replayed `expired` any more than
     * the order would be.
     *
     * @param PaymentStatus $status
     * @return void
     */
    public function setStatus(PaymentStatus $status): void
    {
        if (!$this->getStatus()->canTransitionTo($status)) {
            return;
        }

        $this->status = $status->value;
        $this->updated = time();
        $this->save();
    }

    /**
     * Whether this is the attempt its order is currently waiting on — the one
     * whose webhooks may move the order, as opposed to a superseded attempt
     * whose news is recorded and goes no further.
     *
     * @return bool
     */
    public function isCurrent(): bool
    {
        $order = $this->order;

        return $order !== null &&
            (string) $order->payment_id === (string) $this->payment_id;
    }
}
