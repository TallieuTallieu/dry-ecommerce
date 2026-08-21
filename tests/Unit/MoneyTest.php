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
 */

use Tests\Support\PercentageTaxRate;
use Tnt\Ecommerce\Money;

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
