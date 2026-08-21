<?php

declare(strict_types=1);

/*
 * The rounding rule, pinned down at its boundaries.
 *
 * Money in this package is integer cents, which makes addition exact but does
 * nothing on its own about a *rate* — VAT at 6/12/21%, a percentage discount —
 * landing between two cents. Tnt\Ecommerce\Money is the one place that decides
 * what happens then, and these are the cases that would change answer if the
 * decision were changed:
 *
 *   1. a result of exactly half a cent rounds away from zero;
 *   2. the rate is applied, and rounded, on the amount it covers — so a
 *      per-line rate is rounded per line, and the total is the sum of the
 *      already-rounded lines rather than the rate applied to the total.
 *
 * The last group pins down where the arithmetic stops. Integer cents are exact
 * over a range rather than everywhere, and both of its edges used to be answers
 * instead of errors: an amount over the ceiling gave a TypeError from deep
 * inside intdiv(), and a rate of 0.000004, INF or NAN gave 0 cents behind a PHP
 * warning. Those are the cases below, held at the exact cent they turn over.
 */

use Tests\Support\PercentageTaxRate;
use Tnt\Ecommerce\AmountTooLarge;
use Tnt\Ecommerce\Money;
use Tnt\Ecommerce\UnsupportedRate;

it('rounds a half cent away from zero', function (): void {
    // 6% of 25 is exactly 1.5 cents.
    expect(Money::percentageOf(25, 6))->toBe(2);

    // 21% of 50 is exactly 10.5 cents.
    expect(Money::percentageOf(50, 21))->toBe(11);

    // And of 250, exactly 52.5.
    expect(Money::percentageOf(250, 21))->toBe(53);
});

it('rounds a negative half cent away from zero too', function (): void {
    expect(Money::percentageOf(-50, 21))->toBe(-11);
    expect(Money::percentageOf(50, -21))->toBe(-11);
    expect(Money::percentageOf(-49, 21))->toBe(-10);
});

it('rounds either side of the half cent the obvious way', function (): void {
    // 6% of 24 is 1.44; of 26, 1.56.
    expect(Money::percentageOf(24, 6))->toBe(1);
    expect(Money::percentageOf(26, 6))->toBe(2);

    // 21% of 49 is 10.29; of 51, 10.71.
    expect(Money::percentageOf(49, 21))->toBe(10);
    expect(Money::percentageOf(51, 21))->toBe(11);
});

it('applies VAT at 6, 12 and 21 percent', function (): void {
    // €19.99, the three Belgian rates.
    expect(Money::percentageOf(1999, 6))->toBe(120); // 119.94
    expect(Money::percentageOf(1999, 12))->toBe(240); // 239.88
    expect(Money::percentageOf(1999, 21))->toBe(420); // 419.79
});

it('never reaches a half cent at 12 percent', function (): void {
    // 12a ≡ 50 (mod 100) has no solution, so 12% cannot land on a tie. The
    // nearest it gets is .48 below and .60 above, and both go the obvious way.
    expect(Money::percentageOf(29, 12))->toBe(3); // 3.48
    expect(Money::percentageOf(30, 12))->toBe(4); // 3.60
});

it('honours a fractional rate', function (): void {
    expect(Money::percentageOf(100, 21.5))->toBe(22); // 21.5 -> 22
    expect(Money::percentageOf(100, 8.5))->toBe(9); // 8.5 -> 9
    expect(Money::percentageOf(100, 0.5))->toBe(1); // 0.5 -> 1
    expect(Money::percentageOf(1000, 0.05))->toBe(1); // 0.5 -> 1
});

it('leaves the trivial cases alone', function (): void {
    expect(Money::percentageOf(0, 21))->toBe(0);
    expect(Money::percentageOf(123456, 0))->toBe(0);
    expect(Money::percentageOf(123456, 100))->toBe(123456);
});

it('stays exact on amounts far past float precision', function (): void {
    // 2^53 + 1 cents: the first integer a float cannot represent. A rate of
    // 100% has to give it back unchanged.
    $beyondFloat = 9007199254740993;

    expect(Money::percentageOf($beyondFloat, 100))->toBe($beyondFloat);
});

it('rounds per line rather than on the total', function (): void {
    $vat = new PercentageTaxRate(21);

    // Two identical lines of €12.50. 21% of each is exactly 2.625 euro, a tie
    // at the cent, so each line rounds up to 263.
    $perLine = $vat->getTax(1250) + $vat->getTax(1250);
    $onTheTotal = $vat->getTax(2500);

    expect($vat->getTax(1250))->toBe(263);
    expect($perLine)->toBe(526);

    // The two answers genuinely differ — this is the choice, not a detail.
    expect($onTheTotal)->toBe(525);
    expect($perLine)->not->toBe($onTheTotal);
});

it(
    'sums already-rounded lines exactly, however many there are',
    function (): void {
        $vat = new PercentageTaxRate(21);

        $total = 0;

        // A hundred lines of €0.12: 21% of 12 is 2.52, so 3 a line.
        for ($i = 0; $i < 100; $i++) {
            $total += $vat->getTax(12);
        }

        expect($total)->toBe(300);
    }
);

it('is exact right up to the ceiling for the rate', function (): void {
    // The amount is multiplied twice before an answer comes back — once by 21,
    // once by 2 to round the half — so the ceiling at 21% is PHP_INT_MAX / 42,
    // not / 21. This is that cent exactly.
    $largest = 219604096115589897;

    expect(Money::percentageOf($largest, 21))->toBe(46116860184273878);
    expect(Money::percentageOf(-$largest, 21))->toBe(-46116860184273878);
});

it('refuses an amount one cent past the ceiling', function (): void {
    $tooLarge = 219604096115589898;

    expect(fn() => Money::percentageOf($tooLarge, 21))->toThrow(
        AmountTooLarge::class
    );
    expect(fn() => Money::percentageOf(-$tooLarge, 21))->toThrow(
        AmountTooLarge::class
    );
});

it('says which amount it refused and what it can hold', function (): void {
    try {
        Money::percentageOf(219604096115589898, 21);
    } catch (AmountTooLarge $refused) {
        expect($refused->getAmount())->toBe(219604096115589898);
        expect($refused->getPercentage())->toBe(21);
        expect($refused->getMaximumAmount())->toBe(219604096115589897);
        expect($refused->getMessage())->toContain('219604096115589897 cents');

        return;
    }

    throw new Exception('The amount was not refused.');
});

it('lets a small rate raise the ceiling it sets', function (): void {
    // The ceiling is the rate's, not the class's: at 1% it is 21 times the
    // amount that 21% allows, and 100% divides down to 1/1 and allows the most
    // of all.
    expect(Money::percentageOf(4000000000000000000, 1))->toBe(
        40000000000000000
    );
    expect(fn() => Money::percentageOf(4611686018427387904, 100))->toThrow(
        AmountTooLarge::class
    );
});

it('honours the finest rate there is', function (): void {
    // 0.0001% of €1,000,000.00 is exactly 100 cents.
    expect(Money::percentageOf(100000000, 0.0001))->toBe(100);
});

it('refuses a rate finer than that', function (): void {
    // This used to be 0 cents off an amount of €1,000,000.00, when the true
    // answer is 4, and nothing said so.
    expect(fn() => Money::percentageOf(100000000, 0.000004))->toThrow(
        UnsupportedRate::class
    );
    expect(fn() => Money::percentageOf(1000, -0.00001))->toThrow(
        UnsupportedRate::class
    );
});

it('still takes a rate of exactly zero', function (): void {
    // 0% is not too fine to hold; it is a rate that takes nothing off, and the
    // refusal above must not swallow it.
    expect(Money::percentageOf(123456, 0))->toBe(0);
    expect(Money::percentageOf(123456, 0.0))->toBe(0);
});

it('refuses a rate that is not a finite percentage', function (): void {
    // Each of these gave 0 cents behind a PHP warning, except 1e18, which gave
    // 186471204942302 cents — an int that had wrapped.
    expect(fn() => Money::percentageOf(100, INF))->toThrow(
        UnsupportedRate::class
    );
    expect(fn() => Money::percentageOf(100, -INF))->toThrow(
        UnsupportedRate::class
    );
    expect(fn() => Money::percentageOf(100, NAN))->toThrow(
        UnsupportedRate::class
    );
    expect(fn() => Money::percentageOf(100, 1e300))->toThrow(
        UnsupportedRate::class
    );
    expect(fn() => Money::percentageOf(100, 1e18))->toThrow(
        UnsupportedRate::class
    );
});

it('names the rate it refused, NAN and all', function (): void {
    try {
        Money::percentageOf(100, NAN);
    } catch (UnsupportedRate $refused) {
        expect($refused->getPercentage())->toBeNan();
        expect($refused->getMessage())->toContain('NAN');

        return;
    }

    throw new Exception('The rate was not refused.');
});

it('multiplies a line out by its quantity', function (): void {
    expect(Money::lineTotal(1250, 2))->toBe(2500);
    expect(Money::lineTotal(325, 3))->toBe(975);
    expect(Money::lineTotal(999, 1))->toBe(999);
    expect(Money::lineTotal(1250, 0))->toBe(0);
});

it('writes cents out as units and hundredths', function (): void {
    expect(Money::toDecimal(1225))->toBe('12.25');
    expect(Money::toDecimal(5250))->toBe('52.50');
    expect(Money::toDecimal(100))->toBe('1.00');
    expect(Money::toDecimal(0))->toBe('0.00');
});

it('pads a hundredth that would otherwise read wrong', function (): void {
    // 5 cents is 0.05 and not 0.5, and 50 cents is 0.50 and not 0.5. Both
    // halves are padded, so neither can be read as the other.
    expect(Money::toDecimal(5))->toBe('0.05');
    expect(Money::toDecimal(50))->toBe('0.50');
    expect(Money::toDecimal(1205))->toBe('12.05');
});

it('keeps the sign in front of a negative amount', function (): void {
    // A reduction is the amount that comes off, not a negative, but a total can
    // go below zero and the minus belongs at the front of the whole figure.
    expect(Money::toDecimal(-1225))->toBe('-12.25');
    expect(Money::toDecimal(-5))->toBe('-0.05');
    expect(Money::toDecimal(-100))->toBe('-1.00');
});

it('writes out amounts a float could not hold', function (): void {
    // A string, not a float, so the whole range of an int survives — including
    // the end of it, where negating the amount as a whole would overflow.
    expect(Money::toDecimal(9007199254740993))->toBe('90071992547409.93');
    expect(Money::toDecimal(PHP_INT_MAX))->toBe('92233720368547758.07');
    expect(Money::toDecimal(PHP_INT_MIN))->toBe('-92233720368547758.08');
});

it('gives back no thousands separator and no symbol', function (): void {
    // Deliberately not a currency format: displaying money is the project's
    // job, and this is only the way out of cents that does not use a float.
    expect(Money::toDecimal(123456789))->toBe('1234567.89');
});
