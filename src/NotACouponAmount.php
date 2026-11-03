<?php

declare(strict_types=1);

namespace Tnt\Ecommerce;

use dry\api\ConvertableToApiException;
use dry\api\Exception as ApiException;
use InvalidArgumentException;

/**
 * A euro amount typed into the coupon admin that {@see Money::fromDecimal()}
 * cannot read — `€12`, `1.250,00`, `12.505`. dry's API answers it as
 * `ecommerce.coupon_amount_invalid` on the field it was typed in: `amount` or
 * `minimum_amount`. See docs/discounts.md#the-shipped-coupon.
 */
final class NotACouponAmount extends InvalidArgumentException implements
    ConvertableToApiException
{
    /**
     * @param string $message
     * @param string $field The admin field the amount was typed in.
     * @param NotAnAmount $previous
     */
    private function __construct(
        string $message,
        private readonly string $field,
        NotAnAmount $previous
    ) {
        parent::__construct($message, 0, $previous);
    }

    /**
     * @param string $field The admin field the amount was typed in.
     * @param NotAnAmount $refusal Money's own refusal.
     * @return self
     */
    public static function in(string $field, NotAnAmount $refusal): self
    {
        return new self($refusal->getMessage(), $field, $refusal);
    }

    /**
     * @return ApiException
     */
    public function to_api_exception(): ApiException
    {
        return new ApiException('ecommerce.coupon_amount_invalid', [
            'field' => $this->field,
        ]);
    }
}
