<?php

namespace Tnt\Ecommerce;

use InvalidArgumentException;

/**
 * A rate {@see Money::percentageOf()} cannot apply.
 *
 * A rate is scaled to whole steps per percent before any multiplying happens,
 * so that the money path stays integer throughout. Two kinds of rate survive
 * that scaling as a number, but not as the rate that was meant:
 *
 * - one finer than the scale can express, which would collapse to 0 and hand
 *   back an amount of nothing — see {@see self::tooFine()};
 * - one that is not a finite percentage at all, or is so large that scaling it
 *   leaves PHP's `int` behind — see {@see self::notRepresentable()}.
 *
 * Both used to return a wrong amount rather than stop: a rate of `0.000004`
 * gave 0 cents, and `INF` and `NAN` gave 0 cents too, each behind nothing more
 * than a PHP warning. A shop that does not surface warnings would have taken
 * those amounts as real. So they are refused here instead, where the message
 * can say which rate was passed and what the supported range is.
 *
 * A rate of exactly 0 is not an error; 0% of anything is 0 cents.
 *
 * Extends `InvalidArgumentException`, as does {@see AmountTooLarge}, so a
 * caller that wants to catch either can catch that.
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
     * A rate an amount cannot have inside it.
     *
     * {@see Money::percentageIn()} divides by 100 plus the rate, so a rate of
     * -100% or below leaves nothing to divide by. There is no amount that
     * contains -100% of itself.
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
     * A rate as text, including the ones PHP refuses to stringify quietly.
     *
     * `NAN` and `INF` are exactly the rates worth naming in a message, and
     * casting either to a string raises a warning of its own, so they are
     * spelled out here.
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
