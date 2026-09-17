<?php

declare(strict_types=1);

/*
 * Lines that hang off other lines — a deposit under its crate, a returnable
 * container under what it contains (sc-11448).
 *
 * The cart had no room for this, so a host modelling mandatory accessories
 * rebuilt the relationship from line OPTIONS on every basket render: canonical
 * JSON strings compared in nested loops, per request. One idea, one
 * representation — the parent is a field of the line, and the merge key
 * includes it.
 *
 * With an InMemoryCartStorage and no database, like the rest of CartTest.
 */

use Tests\Support\FakeBuyable;

it(
    'keeps a line under a parent apart from the same line loose',
    function (): void {
        [$cart] = makeCart();

        $crate = new FakeBuyable('crate', 1500);
        $deposit = new FakeBuyable('deposit', 300);

        $cart->add($crate);
        [$crateLine] = $cart->items();

        // The same buyable, once on its own and once under the crate. Two lines:
        // a deposit that merged into the loose one would be a deposit belonging
        // to nothing.
        $cart->add($deposit);
        $cart->add($deposit, 1, [], $crateLine);

        expect($cart->items())->toHaveCount(3);
    }
);

it('merges a line under the same parent', function (): void {
    [$cart] = makeCart();

    $crate = new FakeBuyable('crate', 1500);
    $deposit = new FakeBuyable('deposit', 300);

    $cart->add($crate);
    [$crateLine] = $cart->items();

    $cart->add($deposit, 1, [], $crateLine);
    $cart->add($deposit, 2, [], $crateLine);

    expect($cart->items())->toHaveCount(2);
    expect($cart->childrenOf($crateLine))->toHaveCount(1);
    expect($cart->childrenOf($crateLine)[0]->getQuantity())->toBe(3);
});

it('keeps two parents their own children', function (): void {
    [$cart] = makeCart();

    $first = new FakeBuyable('crate-a', 1500);
    $second = new FakeBuyable('crate-b', 1800);
    $deposit = new FakeBuyable('deposit', 300);

    $cart->add($first);
    $cart->add($second);
    [$firstLine, $secondLine] = $cart->items();

    $cart->add($deposit, 1, [], $firstLine);
    $cart->add($deposit, 1, [], $secondLine);

    // One deposit for two crates is exactly the bug the parent joins the
    // merge key to prevent.
    expect($cart->childrenOf($firstLine))->toHaveCount(1);
    expect($cart->childrenOf($secondLine))->toHaveCount(1);
    expect($cart->items())->toHaveCount(4);
});

it('takes the children out with the parent', function (): void {
    [$cart] = makeCart();

    $crate = new FakeBuyable('crate', 1500);
    $deposit = new FakeBuyable('deposit', 300);

    $cart->add($crate);
    [$crateLine] = $cart->items();
    $cart->add($deposit, 1, [], $crateLine);

    // A deposit whose crate has left the basket is not a thing the shop
    // sells. The foreign key only stops the row pointing at nothing; this is
    // the storage's own rule.
    $cart->removeItem($crateLine->getId());

    expect($cart->items())->toBe([]);
});

it('takes the children out when a parent is zeroed', function (): void {
    [$cart] = makeCart();

    $crate = new FakeBuyable('crate', 1500);
    $deposit = new FakeBuyable('deposit', 300);

    $cart->add($crate);
    [$crateLine] = $cart->items();
    $cart->add($deposit, 1, [], $crateLine);

    // Zero is a removal, so it has to be the same removal.
    $cart->updateQuantity($crateLine->getId(), 0);

    expect($cart->items())->toBe([]);
});

it('leaves a sibling standing when one parent goes', function (): void {
    [$cart] = makeCart();

    $first = new FakeBuyable('crate-a', 1500);
    $second = new FakeBuyable('crate-b', 1800);
    $deposit = new FakeBuyable('deposit', 300);

    $cart->add($first);
    $cart->add($second);
    [$firstLine, $secondLine] = $cart->items();

    $cart->add($deposit, 1, [], $firstLine);
    $cart->add($deposit, 1, [], $secondLine);

    $cart->removeItem($firstLine->getId());

    expect($cart->items())->toHaveCount(2);
    expect($cart->childrenOf($secondLine))->toHaveCount(1);
});

it('still counts a child buyable against its stock', function (): void {
    [$cart, $storage] = makeCart();

    $crate = new FakeBuyable('crate', 1500);
    $deposit = new FakeBuyable('deposit', 300);

    $cart->add($crate);
    [$crateLine] = $cart->items();

    $cart->add($deposit, 2);
    $cart->add($deposit, 3, [], $crateLine);

    // The parent is part of the LINE's identity, not of the buyable's: stock
    // counts the buyable, so both lines are the same five.
    expect($storage->quantityOf($deposit))->toBe(5);
});

it('removes every variant of a buyable, parented or not', function (): void {
    [$cart] = makeCart();

    $crate = new FakeBuyable('crate', 1500);
    $deposit = new FakeBuyable('deposit', 300);

    $cart->add($crate);
    [$crateLine] = $cart->items();

    $cart->add($deposit);
    $cart->add($deposit, 1, [], $crateLine);

    $cart->remove($deposit);

    expect($cart->items())->toHaveCount(1);
    expect($cart->childrenOf($crateLine))->toBe([]);
});

it('answers no children for a line that has none', function (): void {
    [$cart] = makeCart();

    $cart->add(new FakeBuyable('crate', 1500));
    [$crateLine] = $cart->items();

    expect($crateLine->getParent())->toBeNull();
    expect($cart->childrenOf($crateLine))->toBe([]);
});
