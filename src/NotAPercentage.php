<?php

declare(strict_types=1);

namespace Tnt\Ecommerce;

use dry\api\ConvertableToApiException;
use dry\api\Exception as ApiException;
use InvalidArgumentException;

/**
 * A coupon percentage that cannot be stored exactly: not a number, finer
 * than a hundredth of a percent, or outside 0.01–100. dry's API answers it
 * as `ecommerce.coupon_percentage_invalid` on the `percent` field. See
 * docs/discounts.md#the-shipped-coupon.
 */
final class NotAPercentage extends InvalidArgumentException implements
    ConvertableToApiException
{
    /**
     * @param string $text What was refused, as given.
     */
    public static function unreadable(string $text): self
    {
        return new self(
            sprintf(
                "A coupon cannot read '%s' as a percentage: digits, with at " .
                    'most two decimals after a comma or a full stop.',
                $text
            )
        );
    }

    /**
     * @param string $text What was refused, as given.
     */
    public static function outOfRange(string $text): self
    {
        return new self(
            sprintf(
                "A coupon cannot take '%s' percent off: a percentage coupon " .
                    'takes between 0.01 and 100 percent.',
                $text
            )
        );
    }

    /**
     * @return ApiException
     */
    public function to_api_exception(): ApiException
    {
        return new ApiException('ecommerce.coupon_percentage_invalid', [
            'field' => 'percent',
        ]);
    }
}
