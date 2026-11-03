<?php

declare(strict_types=1);

namespace Tnt\Ecommerce;

use InvalidArgumentException;

/**
 * A code {@see \Tnt\Ecommerce\Model\Coupon::save()} will not give a coupon:
 * another coupon holds it, or the coupon has been used under its current
 * code and a customer may still hold that one. See
 * docs/discounts.md#codes.
 */
final class CouponCodeRefused extends InvalidArgumentException
{
    /**
     * @param string $code Normalised.
     */
    public static function taken(string $code): self
    {
        return new self(
            sprintf("The code '%s' already belongs to another coupon.", $code)
        );
    }

    /**
     * @param string $code The code the coupon keeps.
     */
    public static function locked(string $code): self
    {
        return new self(
            sprintf(
                "This coupon has been used as '%s', so its code can no " .
                    'longer change.',
                $code
            )
        );
    }
}
