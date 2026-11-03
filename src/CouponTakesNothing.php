<?php

declare(strict_types=1);

namespace Tnt\Ecommerce;

use dry\api\ConvertableToApiException;
use dry\api\Exception as ApiException;
use InvalidArgumentException;
use Tnt\Ecommerce\Discount\CouponType;

/**
 * A coupon that would take off nothing: a fixed amount of 0 or less, or no
 * percentage at all. dry's API answers it as `ecommerce.coupon_value_invalid`
 * on the field that holds the value — `amount` for a fixed coupon, `percent`
 * for a percentage. See docs/discounts.md#the-shipped-coupon.
 */
final class CouponTakesNothing extends InvalidArgumentException implements
    ConvertableToApiException
{
    /**
     * @param string $message
     * @param string $field The admin field that holds the value.
     */
    private function __construct(
        string $message,
        private readonly string $field
    ) {
        parent::__construct($message);
    }

    /**
     * @param CouponType $type
     * @return self
     */
    public static function of(CouponType $type): self
    {
        return $type === CouponType::Percentage
            ? new self(
                'A percentage coupon takes off more than nothing.',
                'percent'
            )
            : new self('A fixed coupon takes off more than nothing.', 'amount');
    }

    /**
     * @return ApiException
     */
    public function to_api_exception(): ApiException
    {
        return new ApiException('ecommerce.coupon_value_invalid', [
            'field' => $this->field,
        ]);
    }
}
