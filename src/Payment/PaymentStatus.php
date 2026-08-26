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
     * Whether a status may replace this one.
     *
     * Webhooks arrive at least once and out of order, so the listeners ask
     * before writing. Only two things are blocked: leaving Paid for anything
     * but Refunded (a late `expired` must not unsay that the money arrived),
     * and leaving Refunded at all. Everything else stays open — failed→paid
     * is an ordinary retry.
     *
     * @param PaymentStatus $to
     * @return bool
     */
    public function canTransitionTo(PaymentStatus $to): bool
    {
        return match ($this) {
            self::Paid => $to === self::Refunded,
            self::Refunded => false,
            default => true,
        };
    }
}
