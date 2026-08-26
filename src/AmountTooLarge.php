<?php

namespace Tnt\Ecommerce;

use InvalidArgumentException;

/**
 * An amount too large for {@see Money::percentageOf()} to stay exact on —
 * refused rather than silently degraded to a float. Seeing this almost always
 * means an amount is not in cents. See docs/money.md.
 */
final class AmountTooLarge extends InvalidArgumentException
{
    /**
     * @param int $amount The amount that was refused, in cents.
     * @param int|float $percentage The rate it was to be applied at.
     * @param int $maximumAmount The largest amount that rate allows, in cents.
     */
    public function __construct(
        private readonly int $amount,
        private readonly int|float $percentage,
        private readonly int $maximumAmount
    ) {
        parent::__construct(
            sprintf(
                'Money cannot apply %s%% to %d cents and stay exact: the ' .
                    'largest amount that rate allows is %d cents. Amounts are ' .
                    'integer cents, so check that this one is not a value that ' .
                    'has been scaled to cents twice.',
                $percentage,
                $amount,
                $maximumAmount
            )
        );
    }

    /**
     * The amount that was refused, in cents.
     */
    public function getAmount(): int
    {
        return $this->amount;
    }

    /**
     * The rate it was to be applied at, as a percentage.
     */
    public function getPercentage(): int|float
    {
        return $this->percentage;
    }

    /**
     * The largest amount that rate can be applied to, in cents.
     */
    public function getMaximumAmount(): int
    {
        return $this->maximumAmount;
    }
}
