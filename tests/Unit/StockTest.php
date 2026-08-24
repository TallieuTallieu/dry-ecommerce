<?php

declare(strict_types=1);

/*
 * What a stock worker does, and in particular what it does when more is taken
 * out than there is.
 *
 * That is a shop's decision rather than the package's, given once when the
 * worker is built: refuse and raise StockWouldGoNegative, or go under and read
 * the negative as what the shop owes. Both are real ways to run a shop, so
 * neither is assumed, and the count is never quietly clamped to zero — a stock
 * that disagrees with what was taken out of it is the one outcome that helps
 * nobody.
 *
 * > These run against Tests\Support\FakeStockWorker, not against the shipped
 * > Tnt\Ecommerce\Stock\StockWorker, which reads ecommerce_stock and
 * > ecommerce_stock_item and cannot be autoloaded outside a booted Dry
 * > application (see tests/Feature/AutoloadTest.php). The fake implements the
 * > same policy against the same exception, so what is pinned below is the
 * > contract both sides of that seam keep. What is *not* covered anywhere is
 * > the shipped worker's SQL, and no test in this suite can cover it until the
 * > package has a database harness.
 */

use Tests\Support\FakeBuyable;
use Tests\Support\FakeStockWorker;
use Tnt\Ecommerce\Stock\StockWouldGoNegative;

it('counts what was put in', function (): void {
    $stock = new FakeStockWorker();
    $buyable = new FakeBuyable('1', 500);

    $stock->increment($buyable, 10);
    $stock->increment($buyable, 5);

    expect($stock->getQuantity($buyable))->toBe(15);
});

it('reports nothing for a buyable it has never held', function (): void {
    $stock = new FakeStockWorker();

    // Never stocked is not the same as unlimited, and not the same as an
    // error either: the stock simply does not know of this buyable.
    expect($stock->getQuantity(new FakeBuyable('1', 500)))->toBe(0);
    expect($stock->isAvailable(new FakeBuyable('1', 500)))->toBeFalse();
});

it('takes stock out', function (): void {
    $stock = new FakeStockWorker();
    $buyable = new FakeBuyable('1', 500);
    $stock->increment($buyable, 10);

    $stock->decrement($buyable, 4);

    expect($stock->getQuantity($buyable))->toBe(6);
});

it('empties a stock exactly', function (): void {
    $stock = new FakeStockWorker();
    $buyable = new FakeBuyable('1', 500);
    $stock->increment($buyable, 3);

    // Down to nothing is not going negative, so this is allowed either way.
    $stock->decrement($buyable, 3);

    expect($stock->getQuantity($buyable))->toBe(0);
});

it('refuses to take out more than it holds', function (): void {
    $stock = new FakeStockWorker();
    $buyable = new FakeBuyable('1', 500);
    $stock->increment($buyable, 2);

    expect(fn() => $stock->decrement($buyable, 5))->toThrow(
        StockWouldGoNegative::class
    );
});

it('leaves the count alone when it refuses', function (): void {
    $stock = new FakeStockWorker();
    $buyable = new FakeBuyable('1', 500);
    $stock->increment($buyable, 2);

    try {
        $stock->decrement($buyable, 5);
    } catch (StockWouldGoNegative) {
        // Deliberately swallowed; the assertion is what did not happen.
    }

    // Neither taken out nor clamped. The refusal is not a partial success.
    expect($stock->getQuantity($buyable))->toBe(2);
});

it('says what it holds and what was asked for', function (): void {
    $stock = new FakeStockWorker();
    $buyable = new FakeBuyable('1', 500);
    $stock->increment($buyable, 2);

    $refused = null;

    try {
        $stock->decrement($buyable, 5);
    } catch (StockWouldGoNegative $caught) {
        $refused = $caught;
    }

    // The figures a shop needs to reconcile by hand, without re-reading the
    // stock to work out what it just failed to do. The first assertion is what
    // makes the rest meaningful: nothing thrown leaves $refused null and fails
    // here rather than skipping quietly past.
    expect($refused)->toBeInstanceOf(StockWouldGoNegative::class);
    expect($refused?->getBuyable())->toBe($buyable);
    expect($refused?->getAvailable())->toBe(2);
    expect($refused?->getRequested())->toBe(5);
    expect($refused?->getShortfall())->toBe(3);
});

it('goes below zero when the shop backorders', function (): void {
    $stock = new FakeStockWorker(allowNegative: true);
    $buyable = new FakeBuyable('1', 500);
    $stock->increment($buyable, 2);

    $stock->decrement($buyable, 5);

    // Minus three is not a broken count; it is three owed. A shop that has
    // said it backorders wants exactly this figure.
    expect($stock->getQuantity($buyable))->toBe(-3);
});

it('counts a backordered stock back up again', function (): void {
    $stock = new FakeStockWorker(allowNegative: true);
    $buyable = new FakeBuyable('1', 500);
    $stock->increment($buyable, 1);
    $stock->decrement($buyable, 4);

    // A delivery arrives and settles what was owed, with one to spare.
    $stock->increment($buyable, 4);

    expect($stock->getQuantity($buyable))->toBe(1);
});

it('has nothing available while it is in the red', function (): void {
    $stock = new FakeStockWorker(allowNegative: true);
    $buyable = new FakeBuyable('1', 500);
    $stock->increment($buyable, 1);
    $stock->decrement($buyable, 3);

    // Owing two is not having some. Allowing the negative changes what
    // decrement() does, not what the shop is told it can sell.
    expect($stock->getQuantity($buyable))->toBe(-2);
    expect($stock->isAvailable($buyable))->toBeFalse();
});

it('ignores taking out of a buyable it has never held', function (): void {
    $stock = new FakeStockWorker();
    $buyable = new FakeBuyable('1', 500);

    // No line to take out of, so nothing happens and nothing is refused —
    // and no line is created at -1 either.
    $stock->decrement($buyable, 3);

    expect($stock->getQuantity($buyable))->toBe(0);
});

it('counts each buyable separately', function (): void {
    $stock = new FakeStockWorker();
    $one = new FakeBuyable('1', 500);
    $two = new FakeBuyable('2', 500);

    $stock->increment($one, 5);
    $stock->increment($two, 1);
    $stock->decrement($one, 5);

    expect($stock->getQuantity($one))->toBe(0);
    expect($stock->getQuantity($two))->toBe(1);
});
