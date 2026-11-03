<?php

declare(strict_types=1);

/*
 * The statements redeem() sends, read off CapturingCoupon one step short of
 * the connection.
 *
 * What these pin down is the shape: claim, take in one statement, settle;
 * LEAST() so the balance never goes below 0; LAST_INSERT_ID(expr) so each
 * payment reads back its own figure; and no transaction of its own, because
 * the project's may be open and dry's START TRANSACTION would commit it.
 * Whether MySQL then behaves under two payments at once — the row lock, the
 * per-connection LAST_INSERT_ID, the unique index — needs a live database
 * this suite does not have.
 */

use Tests\Support\CapturingCoupon;
use Tests\Support\FakeDiscountCode;
use Tests\Support\InMemoryOrder;

/**
 * @param CapturingCoupon $coupon
 * @return InMemoryOrder
 */
function orderQuoting(CapturingCoupon $coupon): InMemoryOrder
{
    $order = new InMemoryOrder();
    $order->id = 12;
    $order->reduction = 3000;
    $order->discount = FakeDiscountCode::forCoupon($coupon);

    return $order;
}

it('claims, takes the balance in one statement and settles', function (): void {
    $coupon = CapturingCoupon::fixed(5000);
    $coupon->id = 7;
    $coupon->lastInsertId = 2000;

    $coupon->redeem(orderQuoting($coupon));

    expect($coupon->statements)->toBe([
        [
            'INSERT INTO `ecommerce_coupon_redemption` ' .
            '(`created`, `coupon`, `order`, `amount`, `shortfall`) ' .
            'VALUES (?, ?, ?, ?, NULL)',
            [1781524800, 7, 12, 3000],
        ],
        [
            'UPDATE `ecommerce_coupon` ' .
            'SET `balance` = `balance` - LAST_INSERT_ID(LEAST(`balance`, ?)), ' .
            '`uses` = `uses` + 1 ' .
            'WHERE `id` = ?',
            [3000, 7],
        ],
        ['SELECT LAST_INSERT_ID()', []],
        [
            'UPDATE `ecommerce_coupon_redemption` ' .
            'SET `shortfall` = ? WHERE `id` = ?',
            [1000, 41],
        ],
    ]);
});

it(
    'counts a percentage use in one statement and reads it back',
    function (): void {
        $coupon = CapturingCoupon::percentage(10);
        $coupon->id = 7;
        $coupon->lastInsertId = 2;

        $coupon->redeem(orderQuoting($coupon));

        expect($coupon->sql()[1])->toBe(
            'UPDATE `ecommerce_coupon` ' .
                'SET `uses` = LAST_INSERT_ID(`uses` + 1) ' .
                'WHERE `id` = ?'
        );

        // Two uses of a single-use coupon: the second is not covered.
        expect(end($coupon->statements))->toBe([
            'UPDATE `ecommerce_coupon_redemption` ' .
            'SET `shortfall` = ? WHERE `id` = ?',
            [3000, 41],
        ]);
    }
);

it('never opens or closes a transaction', function (): void {
    $fixed = CapturingCoupon::fixed(5000);
    $fixed->id = 7;
    $fixed->redeem(orderQuoting($fixed));

    $percentage = CapturingCoupon::percentage(10);
    $percentage->id = 8;
    $percentage->redeem(orderQuoting($percentage));

    foreach ([...$fixed->sql(), ...$percentage->sql()] as $sql) {
        expect(strtoupper($sql))->not->toContain('TRANSACTION');
        expect(strtoupper($sql))->not->toContain('COMMIT');
        expect(strtoupper($sql))->not->toContain('ROLLBACK');
        expect(strtoupper($sql))->not->toStartWith('BEGIN');
    }
});

it(
    'reads no LAST_INSERT_ID() when the update matched no row',
    function (): void {
        // The claim's INSERT set LAST_INSERT_ID too: read after an UPDATE that
        // matched nothing, it would hand the claim's id back as an amount.
        $coupon = CapturingCoupon::fixed(5000);
        $coupon->id = 7;
        $coupon->affectedRows = 0;

        $coupon->redeem(orderQuoting($coupon));

        expect($coupon->sql())->not->toContain('SELECT LAST_INSERT_ID()');
        expect(end($coupon->statements))->toBe([
            'UPDATE `ecommerce_coupon_redemption` ' .
            'SET `shortfall` = ? WHERE `id` = ?',
            [3000, 41],
        ]);
    }
);

it('stops at the claim for an order already redeemed', function (): void {
    $coupon = CapturingCoupon::fixed(5000);
    $coupon->id = 7;
    $coupon->alreadyClaimed = true;

    $coupon->redeem(orderQuoting($coupon));

    expect($coupon->statements)->toHaveCount(1);
    expect($coupon->sql()[0])->toStartWith(
        'INSERT INTO `ecommerce_coupon_redemption`'
    );
});
