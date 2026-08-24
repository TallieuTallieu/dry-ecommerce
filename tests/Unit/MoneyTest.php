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

use Tnt\Ecommerce\AmountTooLarge;
use Tnt\Ecommerce\Money;
use Tnt\Ecommerce\NotAnAmount;
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
    // Two identical lines of €12.50. 21% of each is exactly 2.625 euro, a tie
    // at the cent, so each line rounds up to 263.
    $perLine = Money::percentageOf(1250, 21) + Money::percentageOf(1250, 21);
    $onTheTotal = Money::percentageOf(2500, 21);

    expect(Money::percentageOf(1250, 21))->toBe(263);
    expect($perLine)->toBe(526);

    // The two answers genuinely differ — this is the choice, not a detail.
    expect($onTheTotal)->toBe(525);
    expect($perLine)->not->toBe($onTheTotal);
});

it(
    'sums already-rounded lines exactly, however many there are',
    function (): void {
        $total = 0;

        // A hundred lines of €0.12: 21% of 12 is 2.52, so 3 a line.
        for ($i = 0; $i < 100; $i++) {
            $total += Money::percentageOf(12, 21);
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

it('reads an amount of units back into cents', function (): void {
    expect(Money::fromDecimal('12.25'))->toBe(1225);
});

it('reads an amount written short', function (): void {
    // What a config value or an admin field holds, rather than what
    // toDecimal() writes: one decimal place, or none at all.
    expect(Money::fromDecimal('12.5'))->toBe(1250);
    expect(Money::fromDecimal('12'))->toBe(1200);
    expect(Money::fromDecimal('0.05'))->toBe(5);
});

it('reads the sign of a negative amount', function (): void {
    expect(Money::fromDecimal('-12.25'))->toBe(-1225);

    // The one the units alone cannot carry: -0 is 0, so the minus has to be
    // read off the amount rather than off the number in front of the point.
    expect(Money::fromDecimal('-0.05'))->toBe(-5);
});

it('reads an amount with space around it', function (): void {
    // Config values and pasted admin input carry it, and it says nothing about
    // the amount either way.
    expect(Money::fromDecimal('  12.25  '))->toBe(1225);
    expect(Money::fromDecimal("12.25\n"))->toBe(1225);
});

it('refuses what is not an amount at all', function (string $text): void {
    // Every one of these is 0 cents to a plain (int) cast, and 0 is a
    // believable amount, so none of them can be allowed to parse.
    expect(fn() => Money::fromDecimal($text))->toThrow(NotAnAmount::class);
})->with(['', 'abc', '12.2.5', '.', '-', '12abc', '1e3']);

it('refuses an amount finer than a cent', function (): void {
    expect(fn() => Money::fromDecimal('12.255'))->toThrow(NotAnAmount::class);
});

it('refuses an amount too large to be cents', function (): void {
    // One cent past PHP_INT_MAX, and one past PHP_INT_MIN. Reading these is
    // where a float last got into the money path: the multiplication overflowed
    // and PHP turned the result into a float without saying so.
    expect(fn() => Money::fromDecimal('92233720368547758.08'))->toThrow(
        NotAnAmount::class
    );
    expect(fn() => Money::fromDecimal('-92233720368547758.09'))->toThrow(
        NotAnAmount::class
    );
});

it('reads the largest amounts there are without a float', function (): void {
    // The cents either side of the refusals above still have to parse, and
    // parse to an int rather than to a float that rounds to one.
    expect(Money::fromDecimal('92233720368547758.07'))->toBe(PHP_INT_MAX);
    expect(Money::fromDecimal('-92233720368547758.08'))->toBe(PHP_INT_MIN);
});

it('reads back exactly what toDecimal wrote', function (int $cents): void {
    // The pair has one job between them, and this is it. Whatever the amount,
    // writing it out and reading it back has to be the amount again — at both
    // ends of an int, where a float would have given up long before.
    expect(Money::fromDecimal(Money::toDecimal($cents)))->toBe($cents);
})->with([
    [0],
    [5],
    [-5],
    [1225],
    [-1225],
    [9007199254740993],
    [PHP_INT_MAX],
    [PHP_INT_MIN],
]);

it('says a sub-cent amount is not merely unreadable', function (): void {
    // Two different problems reach the same class, and the message is the only
    // thing that tells a developer which one they have. '12.255' is a
    // well-formed amount that this package will not round on their behalf.
    try {
        Money::fromDecimal('12.255');
    } catch (NotAnAmount $refused) {
        expect($refused->getText())->toBe('12.255');
        expect($refused->getMessage())->toContain('finer than a cent');

        return;
    }

    throw new Exception('The amount was not refused.');
});

/*
 * The tax already inside an amount, and splitting an amount up without losing
 * any of it. Both arrived with sc-11195, and both exist because a shop quoting
 * tax-inclusive prices needs arithmetic the package did not have.
 */

it('finds the rate already inside an amount', function (): void {
    // €12.50 with 21% in it: the VAT is 1250 x 21/121 = 216.94, not 262.50.
    expect(Money::percentageIn(1250, 21))->toBe(217);

    // The three Belgian rates on €19.99 inclusive.
    expect(Money::percentageIn(1999, 6))->toBe(113); // 113.15
    expect(Money::percentageIn(1999, 12))->toBe(214); // 214.18
    expect(Money::percentageIn(1999, 21))->toBe(347); // 346.98
});

it('is not the same sum as adding the rate on', function (): void {
    // The distinction the whole convention rests on. Using one where the other
    // belongs over-reports by the rate itself, which on an invoice is wrong
    // without looking wrong.
    expect(Money::percentageIn(10_000, 21))->toBe(1736);
    expect(Money::percentageOf(10_000, 21))->toBe(2100);
});

it('leaves an amount whole once the rate is taken out', function (): void {
    // What makes it the right formula: the net plus the tax is the price the
    // customer was quoted, to the cent, with nothing left over.
    foreach ([1250, 1999, 4999, 7, 33, 100_000] as $gross) {
        $tax = Money::percentageIn($gross, 21);

        expect(Money::percentageOf($gross - $tax, 21))->toBe($tax);
    }
});

it('finds nothing inside an amount at no rate', function (): void {
    expect(Money::percentageIn(1250, 0))->toBe(0);
    expect(Money::percentageIn(0, 21))->toBe(0);
});

it('refuses a rate an amount cannot contain', function (): void {
    // Dividing by 100 plus the rate leaves nothing to divide by at -100%.
    expect(fn() => Money::percentageIn(1250, -100))->toThrow(
        UnsupportedRate::class
    );
    expect(fn() => Money::percentageIn(1250, -150))->toThrow(
        UnsupportedRate::class
    );
});

it('splits an amount so the parts add back up to it', function (): void {
    // 250 across 2000 and 500: exactly 200 and 50.
    expect(Money::apportion(250, [2000, 500]))->toBe([200, 50]);

    // And where it does not divide evenly, the cents left over are handed out
    // rather than dropped.
    $shares = Money::apportion(100, [1, 1, 1]);

    expect(array_sum($shares))->toBe(100);
    expect($shares)->toBe([34, 33, 33]);
});

it('never loses or invents a cent, whatever the weights', function (): void {
    // The property that matters. A reduction that does not arrive at the lines
    // complete is tax charged on money nobody paid.
    $cases = [
        [250, [2000, 500]],
        [1, [1, 1, 1, 1, 1]],
        [999, [333, 333, 334]],
        [7, [1000]],
        [12_345, [1, 2, 3, 4, 5, 6, 7, 8, 9, 10]],
        [0, [500, 500]],
    ];

    foreach ($cases as [$amount, $weights]) {
        expect(array_sum(Money::apportion($amount, $weights)))->toBe($amount);
    }
});

it(
    'gives the odd cent to the largest remainder, then the earliest',
    function (): void {
        // Deterministic, so the same cart always splits the same way. Equal
        // weights with one cent spare give it to the first line every time rather
        // than to whichever the sort happened to reach.
        expect(Money::apportion(3, [1, 1]))->toBe([2, 1]);
        expect(Money::apportion(3, [1, 1]))->toBe([2, 1]);
    }
);

it('apportions nothing across nothing', function (): void {
    // An empty cart, or a cart of free things, has no weights to spread a
    // reduction over. Zero shares rather than a division by zero.
    expect(Money::apportion(500, [0, 0]))->toBe([0, 0]);
    expect(Money::apportion(500, []))->toBe([]);
});
