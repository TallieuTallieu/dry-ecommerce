<?php

namespace Tnt\Ecommerce;

use InvalidArgumentException;

/**
 * An amount too large for {@see Money::percentageOf()} to stay exact on.
 *
 * Money is an `int` number of cents, and applying a rate to it multiplies it
 * twice: once by the rate, and once more by 2 to round the half away from zero
 * with integers only. Past a certain amount that product no longer fits in a
 * PHP `int`, and PHP would quietly turn it into a `float` — which is the one
 * thing this package exists to keep out of the money path.
 *
 * So the amount is refused instead. The ceiling depends on the rate, and
 * {@see self::getMaximumAmount()} is the largest amount that rate can be
 * applied to: roughly 2.19×10^17 cents at 21%, which is far past any real
 * order. Seeing this exception almost always means an amount is not in cents —
 * a value already multiplied by 100 twice, say.
 *
 * Extends `InvalidArgumentException`, as does {@see UnsupportedRate}, so a
 * caller that wants to catch either can catch that.
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
