<?php

declare(strict_types=1);

namespace Tnt\Ecommerce;

use dry\api\ConvertableToApiException;
use dry\api\Exception as ApiException;
use InvalidArgumentException;

/**
 * A code {@see \Tnt\Ecommerce\Model\Coupon::save()} will not give a coupon:
 * another coupon holds it, or the coupon has been used under its current
 * code and a customer may still hold that one. dry's API answers it as
 * `ecommerce.coupon_code_taken` or `ecommerce.coupon_code_locked` on the
 * `code` field. See docs/discounts.md#codes.
 */
final class CouponCodeRefused extends InvalidArgumentException implements
    ConvertableToApiException
{
    /**
     * @param string $message
     * @param string $errorCode What dry's API answers.
     */
    private function __construct(
        string $message,
        private readonly string $errorCode
    ) {
        parent::__construct($message);
    }

    /**
     * @param string $code Normalised.
     */
    public static function taken(string $code): self
    {
        return new self(
            sprintf("The code '%s' already belongs to another coupon.", $code),
            'ecommerce.coupon_code_taken'
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
            ),
            'ecommerce.coupon_code_locked'
        );
    }

    /**
     * @return ApiException
     */
    public function to_api_exception(): ApiException
    {
        return new ApiException($this->errorCode, ['field' => 'code']);
    }
}
