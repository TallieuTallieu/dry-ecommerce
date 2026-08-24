<?php

declare(strict_types=1);

/*
 * The row-backed cart line, and the buyable it only loads once.
 *
 * Tnt\Ecommerce\Model\CartItem stores a class name and a foreign id rather than
 * a real foreign key, so getBuyable() has to go and fetch the thing. dry's
 * Model::load() is a plain SELECT with no identity map behind it, and nearly
 * every other method here goes through getBuyable() — getTitle(),
 * getDescription() and getPrice() all do, as do Cart::getSubTotal(),
 * Cart::getTax() and Order::add(), which asks twice in two consecutive lines.
 * Each of those was its own query.
 *
 * Holding the buyable once it has been fetched is what these tests are about,
 * and the memo is observable without a database in exactly one way: a line that
 * was *handed* its buyable never needs to fetch it, so everything below runs
 * with no connection anywhere near it. Before the memo the same code reached
 * for dry\db\Connection and could not have run outside a booted Dry
 * application at all.
 *
 * > What is not covered here is the other half — a line read back from
 * > ecommerce_cart_item, where the first getBuyable() does query and the second
 * > must not. That needs a database harness the package does not have yet, and
 * > the same gap applies to Model\OrderItem, which carries the identical memo
 * > and has no setBuyable() to prime it with.
 */

use Tests\Support\FakeBuyable;
use Tnt\Ecommerce\Model\CartItem;

it('hands back the buyable it was given', function (): void {
    $item = new CartItem();
    $buyable = new FakeBuyable('7', 500);

    $item->setBuyable($buyable);

    // The same instance, not an equal one: nothing was fetched to produce it.
    expect($item->getBuyable())->toBe($buyable);
    expect($item->getBuyable())->toBe($item->getBuyable());
});

it('answers about that buyable without a database', function (): void {
    $item = new CartItem();
    $item->setBuyable(new FakeBuyable('7', 500, 'A memoised thing'));
    $item->quantity = 3;

    // Four calls that each cost their own SELECT before the memo, on a line
    // that has never been near a connection.
    expect($item->getTitle())->toBe('A memoised thing');
    expect($item->getDescription())->toBe('Description of A memoised thing');
    expect($item->getQuantity())->toBe(3);
    expect($item->getPrice())->toBe(1500);
});

it('records the class and id the storages key on', function (): void {
    $item = new CartItem();
    $buyable = new FakeBuyable('7', 500);

    $item->setBuyable($buyable);

    // Memoising the instance must not stop the columns being written: the
    // line is found again by these two, and by nothing else.
    expect($item->item_class)->toBe(FakeBuyable::class);
    expect($item->item_id)->toBe(7);
});

it(
    'follows the buyable when the line is pointed at another',
    function (): void {
        $item = new CartItem();
        $item->setBuyable(new FakeBuyable('7', 500, 'The first thing'));

        $replacement = new FakeBuyable('8', 250, 'The second thing');
        $item->setBuyable($replacement);

        // A stale memo would be worse than no memo, so setBuyable() replaces it
        // rather than leaving the first one in place.
        expect($item->getBuyable())->toBe($replacement);
        expect($item->getTitle())->toBe('The second thing');
        expect($item->item_id)->toBe(8);
    }
);
