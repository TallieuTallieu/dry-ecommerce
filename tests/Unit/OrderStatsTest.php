<?php

use dry\util\Period;
use Tnt\Ecommerce\Order\OrderFigures;
use Tnt\Ecommerce\Order\OrderStats;

/**
 * Orders over a period: one query, each order into its bucket.
 */
function stats_period(string $from, string $to): Period
{
    return new Period(new DateTimeImmutable($from), new DateTimeImmutable($to));
}

test(
    'one query sorts orders into the buckets, a placeholder per parameter',
    function () {
        $period = stats_period('2026-10-08', '2026-10-10');
        [$sql, $parameters] = (new OrderStats())->query($period);

        expect($sql)->toContain('INTERVAL(`created`, ?, ?) AS `bucket`');
        expect($sql)->toContain('GROUP BY `bucket`');
        expect(substr_count($sql, '?'))->toBe(count($parameters));

        [$from, $to] = $period->span();
        expect(array_slice($parameters, -4))->toBe(['placed', '', $from, $to]);
    }
);

test('the comparison reads the same buckets a year back', function () {
    $period = stats_period('2026-10-08', '2026-10-10');
    [, $parameters] = (new OrderStats())->query($period, true);

    expect(array_slice($parameters, 0, 2))->toBe(
        array_slice($period->bounds(true), 1, 2)
    );
});

test('a shop narrows the orders with a scope of its own', function () {
    $stats = new OrderStats(fn() => ['`canceled` IS NULL AND `x` <> ?', 'y']);
    [$sql, $parameters] = $stats->query(
        stats_period('2026-10-01', '2026-10-01')
    );

    expect($sql)->toContain('AND (`canceled` IS NULL AND `x` <> ?)');
    expect($sql)->toContain('0 AS `bucket`');
    expect(end($parameters))->toBe('y');
    expect(substr_count($sql, '?'))->toBe(count($parameters));
});

test('the totals add the buckets up', function () {
    expect(
        (new OrderFigures([1, 2], [1, 0], [500, 700], [500, 0]))->totals()
    )->toBe([
        'orders' => 3,
        'paid' => 1,
        'revenue' => 1200,
        'paid_revenue' => 500,
    ]);
});
