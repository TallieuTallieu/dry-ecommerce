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
}
