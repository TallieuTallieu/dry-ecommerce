<?php

declare(strict_types=1);

namespace Tnt\Ecommerce;

use RuntimeException;

/**
 * A webhook named a payment id no order carries. Loud rather than a no-op:
 * answering the provider with an error is what makes it retry, and a shop
 * being posted ids it never issued should get to see that. See
 * docs/payment.md.
 */
final class UnknownPayment extends RuntimeException
{
    private function __construct(string $message)
    {
        parent::__construct($message);
    }

    /**
     * @param string $paymentId The id the webhook named.
     * @return self
     */
    public static function id(string $paymentId): self
    {
        return new self(
            sprintf(
                "No order carries payment id '%s'. Either the id never came " .
                    'from this shop, or the order it belonged to is gone.',
                $paymentId
            )
        );
    }
}
