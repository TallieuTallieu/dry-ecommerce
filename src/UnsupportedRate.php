<?php

namespace Tnt\Ecommerce;

use InvalidArgumentException;

/**
 * A rate {@see Money::percentageOf()} cannot apply: too fine for the scale,
 * not a finite percentage, or (for percentageIn) -100% or below. A rate of
 * exactly 0 is fine. See docs/money.md.
 */
final class UnsupportedRate extends InvalidArgumentException
{
    /**
     * @param int|float $percentage The rate that was refused.
     * @param string $message
     */
    private function __construct(
        private readonly int|float $percentage,
        string $message
    ) {
        parent::__construct($message);
    }

    /**
     * A non-zero rate too fine for the scale to hold.
     *
     * @param int|float $percentage The rate that was refused.
     * @param float $finest The finest non-zero rate, as a percentage.
     * @return self
     */
    public static function tooFine(int|float $percentage, float $finest): self
    {
        return new self(
            $percentage,
            sprintf(
                'Money cannot apply a rate of %s%%: rates are honoured to %s%% ' .
                    'and no finer, and this one would round to 0%% and take ' .
                    'nothing off. Pass 0 if no rate at all was meant.',
                self::describe($percentage),
                self::describe($finest)
            )
        );
    }

    /**
     * A rate that is not a finite percentage, or is too large to scale.
     *
     * @param int|float $percentage The rate that was refused.
     * @return self
     */
    public static function notRepresentable(int|float $percentage): self
    {
        return new self(
            $percentage,
            sprintf(
                'Money cannot apply a rate of %s%%: a rate has to be a finite ' .
                    'percentage that is still a whole number once scaled, and ' .
                    'this one is not.',
                self::describe($percentage)
            )
        );
    }

    /**
     * A rate an amount cannot have inside it — -100% or below leaves nothing
     * to divide by.
     *
     * @param int|float $percentage The rate that was refused.
     * @return self
     */
    public static function cannotBeContained(int|float $percentage): self
    {
        return new self(
            $percentage,
            sprintf(
                'Money cannot find a rate of %s%% inside an amount: an amount ' .
                    'that contains a rate is 100%% plus that rate, and this ' .
                    'one leaves nothing for the amount to be.',
                self::describe($percentage)
            )
        );
    }

    /**
     * The rate that was refused, as a percentage.
     */
    public function getPercentage(): int|float
    {
        return $this->percentage;
    }

    /**
     * A rate as text — `NAN` and `INF` spelled out, since casting either
     * raises a warning of its own.
     *
     * @param int|float $percentage
     * @return string
     */
    private static function describe(int|float $percentage): string
    {
        if (is_float($percentage)) {
            if (is_nan($percentage)) {
                return 'NAN';
            }

            if (is_infinite($percentage)) {
                return $percentage > 0 ? 'INF' : '-INF';
            }
        }

        return (string) $percentage;
    }
}
