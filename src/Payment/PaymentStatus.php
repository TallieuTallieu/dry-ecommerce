<?php

declare(strict_types=1);

namespace Tnt\Ecommerce\Payment;

/**
 * Where the money for an order stands — the payment's state, not a
 * fulfillment status. Written only by the package: `pending` at checkout,
 * every later value by the event listeners. See docs/payment.md.
 */
enum PaymentStatus: string
{
    /**
     * The order exists and the money has not arrived. Every order starts here.
     */
    case Pending = 'pending';

    /**
     * The money arrived.
     */
    case Paid = 'paid';

    /**
     * The attempt failed.
     */
    case Failed = 'failed';

    /**
     * The customer backed out.
     */
    case Canceled = 'canceled';

    /**
     * The payment window closed without payment.
     */
    case Expired = 'expired';

    /**
     * The money went back.
     */
    case Refunded = 'refunded';

    /**
     * Part of the money went back — a goodwill gesture, one returned line.
     * The order is still an order somebody paid for.
     */
    case PartiallyRefunded = 'partially_refunded';

    /**
     * Whether a status may replace this one.
     *
     * Webhooks arrive at least once and out of order, so the listeners ask
     * before writing. Three rules: Paid may only be left for a refund of
     * either size (a late `expired` must not unsay that the money arrived);
     * a partial refund may only deepen into a full one; and a full refund
     * opens exactly one door, back to Pending, which is the re-placement
     * {@see \Tnt\Ecommerce\Model\Order::isRePlaceable()} reads this for.
     * Everything else stays open — failed→paid is an ordinary retry.
     *
     * @param PaymentStatus $to
     * @return bool
     */
    public function canTransitionTo(PaymentStatus $to): bool
    {
        return match ($this) {
            self::Paid => $to === self::Refunded ||
                $to === self::PartiallyRefunded,
            self::PartiallyRefunded => $to === self::Refunded,
            self::Refunded => $to === self::Pending,
            default => true,
        };
    }
}
