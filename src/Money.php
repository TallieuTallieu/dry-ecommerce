<?php

namespace Tnt\Ecommerce;

/**
 * Money as integer cents, and the one rounding rule the package applies to it:
 * round half away from zero, once, on the smallest amount the rate genuinely
 * applies to. What it cannot do exactly it refuses ({@see AmountTooLarge},
 * {@see UnsupportedRate}, {@see NotApportionable}). See docs/money.md.
 */
final class Money
{
    /**
     * Integer steps per percent — rates are exact to 0.0001%; a non-zero rate
     * finer than that raises {@see UnsupportedRate}.
     */
    private const RATE_SCALE = 10000;

    /**
     * Static only; there is no `Money` value to hold, only `int` cents.
     */
    private function __construct() {}

    /**
     * A percentage of an amount, in cents, rounded half away from zero — the
     * single place the rounding rule is implemented. Both arguments may be
     * negative.
     *
     * @param int $amount The amount the rate applies to, in cents.
     * @param int|float $percentage The rate, as a percentage: 21 means 21%.
     * @return int The rounded result, in cents.
     *
     * @throws AmountTooLarge If the amount is over the ceiling for this rate.
     * @throws UnsupportedRate If the rate is finer, or larger, than the scale
     *                         can hold, or is not a finite percentage.
     */
    public static function percentageOf(int $amount, int|float $percentage): int
    {
        [$rateNumerator, $rateDenominator] = self::reduceRate($percentage);

        self::refuseAnAmountOverTheCeiling(
            $amount,
            $percentage,
            $rateNumerator,
            $rateDenominator
        );

        return self::divideRoundingHalfAwayFromZero(
            $amount * $rateNumerator,
            $rateDenominator
        );
    }

    /**
     * The part of an amount that *is* the percentage — `amount x r/(100 + r)`,
     * rounded half away from zero. The tax-inclusive counterpart of
     * {@see percentageOf()}; see {@see \Tnt\Ecommerce\Tax\PriceConvention}.
     *
     * @param int $amount The amount with the percentage already in it, in cents.
     * @param int|float $percentage The rate, as a percentage: 21 means 21%.
     * @return int The part of it that is the rate, in cents.
     *
     * @throws AmountTooLarge If the amount is over the ceiling for this rate.
     * @throws UnsupportedRate If the rate is one the scale cannot hold, or is
     *                         -100% or below, at which point an amount cannot
     *                         contain it.
     */
    public static function percentageIn(int $amount, int|float $percentage): int
    {
        [$rateNumerator, $rateDenominator] = self::reduceRate($percentage);

        // The whole of the amount is 100% *plus* the rate, so the denominator
        // carries the rate as well.
        $whole = $rateDenominator + $rateNumerator;

        if ($whole <= 0) {
            throw UnsupportedRate::cannotBeContained($percentage);
        }

        $divisor = self::greatestCommonDivisor(
            $rateNumerator < 0 ? -$rateNumerator : $rateNumerator,
            $whole
        );

        $numerator = intdiv($rateNumerator, $divisor);
        $denominator = intdiv($whole, $divisor);

        self::refuseAnAmountOverTheCeiling(
            $amount,
            $percentage,
            $numerator,
            $denominator
        );

        return self::divideRoundingHalfAwayFromZero(
            $amount * $numerator,
            $denominator
        );
    }

    /**
     * Split an amount across weights so the parts add back up to it exactly —
     * largest remainder method, ties to the earlier weight. Whatever the
     * weights, `array_sum($result) === $amount`.
     *
     * @param int $amount The amount to split, in cents.
     * @param array<int, int> $weights What to split it in proportion to.
     * @return array<int, int> One share per weight, in the same order, summing
     *                         to `$amount`. All zero when the weights are.
     *
     * @throws NotApportionable If the amount, or any weight, is negative.
     */
    public static function apportion(int $amount, array $weights): array
    {
        if ($amount < 0) {
            throw NotApportionable::negativeAmount($amount);
        }

        $total = 0;

        foreach ($weights as $index => $weight) {
            if ($weight < 0) {
                throw NotApportionable::negativeWeight($weight, $index);
            }

            $total += $weight;
        }

        if ($total === 0) {
            return array_fill_keys(array_keys($weights), 0);
        }

        $shares = [];
        $remainders = [];

        foreach ($weights as $index => $weight) {
            $product = $amount * $weight;

            $shares[$index] = intdiv($product, $total);
            $remainders[$index] = $product % $total;
        }

        // What flooring every share left behind, handed out a cent at a time.
        $left = $amount - array_sum($shares);

        if ($left > 0) {
            arsort($remainders);

            foreach (array_slice(array_keys($remainders), 0, $left) as $index) {
                $shares[$index]++;
            }
        }

        return $shares;
    }

    /**
     * What a line of a cart or an order comes to, in cents.
     *
     * @param int $unitPrice The price of one, in cents.
     * @param int $quantity How many.
     * @return int The line total, in cents.
     */
    public static function lineTotal(int $unitPrice, int $quantity): int
    {
        return $unitPrice * $quantity;
    }

    /**
     * An amount of cents written out in units, exactly: `1225` -> `'12.25'`.
     * Deliberately not a currency format — no symbol, no locale; it exists to
     * replace `$cents / 100`. See docs/money.md.
     *
     * @param int $cents
     * @return string
     */
    public static function toDecimal(int $cents): string
    {
        $sign = $cents < 0 ? '-' : '';

        // Each part is negated on its own: negating the amount as a whole
        // would overflow on PHP_INT_MIN.
        $units = intdiv($cents, 100);
        $hundredths = $cents % 100;

        return sprintf(
            '%s%d.%02d',
            $sign,
            $units < 0 ? -$units : $units,
            $hundredths < 0 ? -$hundredths : $hundredths
        );
    }

    /**
     * An amount written in units, read back into cents: `'12.25'` -> `1225`.
     * No locale — anything that is not an exact amount of cents raises
     * {@see NotAnAmount}. See docs/money.md.
     *
     * @param string $amount
     * @return int The amount, in cents.
     *
     * @throws NotAnAmount If the text is not an exact amount of cents.
     */
    public static function fromDecimal(string $amount): int
    {
        $trimmed = trim($amount);

        if (preg_match('/^(-?)(\d+)(?:\.(\d+))?$/', $trimmed, $parts) !== 1) {
            throw NotAnAmount::unreadable($amount);
        }

        $decimals = $parts[3] ?? '';

        if (strlen($decimals) > 2) {
            throw NotAnAmount::finerThanACent($amount);
        }

        // Appending two decimal places to the units *is* the multiplication by
        // 100, done in text — nothing here can overflow into a float.
        $digits = ltrim($parts[2] . str_pad($decimals, 2, '0'), '0');
        $cents = filter_var(
            $parts[1] . ($digits === '' ? '0' : $digits),
            FILTER_VALIDATE_INT
        );

        if ($cents === false) {
            throw NotAnAmount::tooLarge($amount);
        }

        return $cents;
    }

    /**
     * A rate as a whole fraction, reduced — the boundary where an incoming
     * `float` rate becomes the integer pair the arithmetic runs on.
     *
     * @param int|float $percentage The rate, as a percentage.
     * @return array{int, int} The numerator, which carries the sign, and the
     *                         denominator, which is positive.
     *
     * @throws UnsupportedRate
     */
    private static function reduceRate(int|float $percentage): array
    {
        $scaled = round((float) $percentage * self::RATE_SCALE);

        // `(float) PHP_INT_MAX` rounds up to 2^63, one past the largest int, so
        // `>=` is what keeps the cast below inside the range PHP guarantees.
        // `is_finite()` covers NAN and INF, which no comparison would catch.
        if (!is_finite($scaled) || abs($scaled) >= (float) PHP_INT_MAX) {
            throw UnsupportedRate::notRepresentable($percentage);
        }

        $numerator = (int) $scaled;

        if ($numerator === 0 && (float) $percentage !== 0.0) {
            throw UnsupportedRate::tooFine($percentage, 1 / self::RATE_SCALE);
        }

        $denominator = 100 * self::RATE_SCALE;

        $divisor = self::greatestCommonDivisor(
            $numerator < 0 ? -$numerator : $numerator,
            $denominator
        );

        return [intdiv($numerator, $divisor), intdiv($denominator, $divisor)];
    }

    /**
     * Refuses an amount this rate cannot be applied to exactly. The ceiling is
     * derived by dividing, not multiplying — working out the limit must not
     * overflow the very thing it is checking for.
     *
     * @param int $amount The amount the rate applies to, in cents.
     * @param int|float $percentage The rate, for the exception to report.
     * @param int $rateNumerator
     * @param int $rateDenominator Positive.
     * @return void
     *
     * @throws AmountTooLarge
     */
    private static function refuseAnAmountOverTheCeiling(
        int $amount,
        int|float $percentage,
        int $rateNumerator,
        int $rateDenominator
    ): void {
        $magnitude = $rateNumerator < 0 ? -$rateNumerator : $rateNumerator;

        // A rate of 0% multiplies everything down to nothing; no amount of
        // cents can overflow that.
        if ($magnitude === 0) {
            return;
        }

        $maximumAmount = intdiv(
            intdiv(PHP_INT_MAX - $rateDenominator, 2),
            $magnitude
        );

        if ($amount > $maximumAmount || $amount < -$maximumAmount) {
            throw new AmountTooLarge($amount, $percentage, $maximumAmount);
        }
    }

    /**
     * Integer division that rounds a half away from zero.
     *
     * `intdiv(2n + d, 2d)` is `floor((n + d/2) / d)`, which is exactly
     * round-half-up for a non-negative `n`; the sign is peeled off first and
     * put back afterwards so that a negative amount rounds away from zero
     * rather than towards it.
     *
     * @param int $numerator
     * @param int $denominator Positive.
     * @return int
     */
    private static function divideRoundingHalfAwayFromZero(
        int $numerator,
        int $denominator
    ): int {
        $isNegative = $numerator < 0;
        $magnitude = $isNegative ? -$numerator : $numerator;

        $rounded = intdiv(2 * $magnitude + $denominator, 2 * $denominator);

        return $isNegative ? -$rounded : $rounded;
    }

    /**
     * Euclid, so that a rate can be reduced to its smallest whole fraction.
     *
     * A zero numerator reduces against the denominator itself, which collapses
     * a rate of 0% to 0/1 rather than dividing by zero.
     *
     * @param int $a Non-negative.
     * @param int $b Positive.
     * @return int
     */
    private static function greatestCommonDivisor(int $a, int $b): int
    {
        while ($b !== 0) {
            [$a, $b] = [$b, $a % $b];
        }

        return $a;
    }
}
