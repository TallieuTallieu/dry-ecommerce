<?php

declare(strict_types=1);

/*
 * Per-line options, and the line identity they made necessary.
 *
 * A cart line is (buyable, options) now, not the buyable alone, and this file
 * pins down everything that follows from that: a different selection is a
 * different line, the same selection assembled in a different order is the
 * same line, no options merges with no options exactly as it did before the
 * options existed, and stock counts the buyable across all of its variants.
 * With one buyable on several lines the buyable stops naming a line, so the
 * line's own id becomes the handle a basket form works with — updateQuantity
 * and removeItem — and those are pinned down here too.
 *
 * Everything runs through makeCart(), so it is the in-memory storage being
 * exercised; the session-backed storage keys its lines with the same
 * LineOptions canonical form through CartItemRepository::forBuyable(), whose
 * composition RepositoryTest covers and whose round trip needs the database
 * harness the suite does not have (see the note in CartItemTest).
 */

use Tests\Support\FakeBuyable;
use Tests\Support\FakeStockedBuyable;
use Tests\Support\FakeStockWorker;

it('keeps differently-optioned lines of one buyable apart', function (): void {
    [$cart] = makeCart();

    // The exact failure the options exist to end: two configurations of the
    // same product used to merge into one line of two with one selection.
    $cart->add(new FakeBuyable('1', 16000), 1, ['cheese' => 'no goat']);
    $cart->add(new FakeBuyable('1', 16000), 1, ['cheese' => 'no blue']);

    expect($cart->items())->toHaveCount(2);
    expect($cart->items()[0]->getQuantity())->toBe(1);
    expect($cart->items()[1]->getQuantity())->toBe(1);
    expect($cart->getSubTotal())->toBe(32000);
});

it('merges the same selection made in a different order', function (): void {
    [$cart] = makeCart();

    // Ticking the same boxes in the other order must be the same line —
    // canonicalisation is what these two adds are exercising, nested level
    // included.
    $cart->add(new FakeBuyable('1', 1000), 1, [
        'size' => 'L',
        'extras' => ['sauce' => 'yes', 'bread' => 'no'],
    ]);
    $cart->add(new FakeBuyable('1', 1000), 2, [
        'extras' => ['bread' => 'no', 'sauce' => 'yes'],
        'size' => 'L',
    ]);

    expect($cart->items())->toHaveCount(1);
    expect($cart->items()[0]->getQuantity())->toBe(3);
});

it('merges a no-options add into a no-options line', function (): void {
    [$cart] = makeCart();

    // The compatibility half of the design: a caller that has never heard of
    // options behaves exactly as it did before they existed.
    $cart->add(new FakeBuyable('1', 1000), 1);
    $cart->add(new FakeBuyable('1', 1000), 2);

    expect($cart->items())->toHaveCount(1);
    expect($cart->items()[0]->getQuantity())->toBe(3);
});

it('keeps a no-options line apart from an optioned one', function (): void {
    [$cart] = makeCart();

    // No options is a variant like any other, not a wildcard: plain tapas and
    // configured tapas are different lines.
    $cart->add(new FakeBuyable('1', 1000), 1);
    $cart->add(new FakeBuyable('1', 1000), 1, ['cheese' => 'no goat']);

    expect($cart->items())->toHaveCount(2);
});

it('hands a line back the options it was added with', function (): void {
    [$cart] = makeCart();

    $cart->add(new FakeBuyable('1', 1000), 1, ['cheese' => 'no goat']);
    $cart->add(new FakeBuyable('1', 1000), 1);

    expect($cart->items()[0]->getOptions())->toBe(['cheese' => 'no goat']);
    expect($cart->items()[1]->getOptions())->toBe([]);
});

it('counts a buyable across all of its variants', function (): void {
    [$cart, $storage] = makeCart();

    $cart->add(new FakeBuyable('1', 1000), 2, ['cheese' => 'no goat']);
    $cart->add(new FakeBuyable('1', 1000), 3, ['cheese' => 'no blue']);
    $cart->add(new FakeBuyable('1', 1000), 1);
    $cart->add(new FakeBuyable('2', 500), 7);

    // The sum, not the count of any one line: this answer feeds canAdd(), and
    // stock counts tapas, not selections of tapas.
    expect($storage->quantityOf(new FakeBuyable('1', 1000)))->toBe(6);
    expect($storage->quantityOf(new FakeBuyable('2', 500)))->toBe(7);
});

it('checks stock against the total over the variants', function (): void {
    $worker = new FakeStockWorker();
    [$cart] = makeCart();

    $buyable = new FakeStockedBuyable('1', 1000, $worker);
    $worker->increment($buyable, 5);

    $cart->add($buyable, 2, ['cheese' => 'no goat']);
    $cart->add($buyable, 2, ['cheese' => 'no blue']);

    // Four in the cart on two lines, five in stock. One more fits; two more
    // would be six of a stock of five, however the lines are optioned. A
    // per-variant count would have said yes to both.
    expect($cart->canAdd($buyable, 1))->toBeTrue();
    expect($cart->canAdd($buyable, 2))->toBeFalse();
});

it('sets a line to a quantity by its id', function (): void {
    [$cart] = makeCart();

    $cart->add(new FakeBuyable('1', 1000), 1, ['cheese' => 'no goat']);
    $cart->add(new FakeBuyable('1', 1000), 1, ['cheese' => 'no blue']);

    $cart->updateQuantity($cart->items()[0]->getId(), 5);

    // Sets, not merges: the quantity is the one asked for, and only the named
    // line moved.
    expect($cart->items()[0]->getQuantity())->toBe(5);
    expect($cart->items()[1]->getQuantity())->toBe(1);
});

it('removes a line set to zero or less', function (int $quantity): void {
    [$cart] = makeCart();

    $cart->add(new FakeBuyable('1', 1000), 3, ['cheese' => 'no goat']);
    $cart->add(new FakeBuyable('2', 500), 1);

    $cart->updateQuantity($cart->items()[0]->getId(), $quantity);

    // "Set it to nothing" and "take it away" are the same request — a basket
    // form's number input reaches zero, and a line of zero is not a line.
    expect($cart->items())->toHaveCount(1);
    expect($cart->items()[0]->getBuyable()->getId())->toBe('2');
})->with([[0], [-1]]);

it('ignores a quantity update for an id it does not hold', function (): void {
    [$cart] = makeCart();

    $cart->add(new FakeBuyable('1', 1000), 2);

    // A stale basket form — the line went in another tab — is ordinary, not
    // an error, so nothing throws and nothing changes.
    $cart->updateQuantity('999', 5);
    $cart->updateQuantity('not-an-id', 5);

    expect($cart->items())->toHaveCount(1);
    expect($cart->items()[0]->getQuantity())->toBe(2);
});

it('removes one line by its id and leaves the variants', function (): void {
    [$cart] = makeCart();

    $cart->add(new FakeBuyable('1', 1000), 1, ['cheese' => 'no goat']);
    $cart->add(new FakeBuyable('1', 1000), 2, ['cheese' => 'no blue']);

    $cart->removeItem($cart->items()[0]->getId());

    // Exactly the named line: the other variant of the same buyable stays,
    // which is what tells removeItem() apart from remove().
    expect($cart->items())->toHaveCount(1);
    expect($cart->items()[0]->getOptions())->toBe(['cheese' => 'no blue']);
});

it('ignores removing an id it does not hold', function (): void {
    [$cart] = makeCart();

    $cart->add(new FakeBuyable('1', 1000), 2);

    $cart->removeItem('999');

    expect($cart->items())->toHaveCount(1);
});

it('removes every variant when handed the buyable', function (): void {
    [$cart, $storage] = makeCart();

    $cart->add(new FakeBuyable('1', 1000), 1, ['cheese' => 'no goat']);
    $cart->add(new FakeBuyable('1', 1000), 2, ['cheese' => 'no blue']);
    $cart->add(new FakeBuyable('1', 1000), 3);
    $cart->add(new FakeBuyable('2', 500), 1);

    // A caller holding a buyable cannot name a variant, so remove() means all
    // of them — anything less would remove an arbitrary one.
    $cart->remove(new FakeBuyable('1', 1000));

    expect($cart->items())->toHaveCount(1);
    expect($cart->items()[0]->getBuyable()->getId())->toBe('2');
    expect($storage->quantityOf(new FakeBuyable('1', 1000)))->toBe(0);
});

it('gives every line its own stable id', function (): void {
    [$cart] = makeCart();

    $cart->add(new FakeBuyable('1', 1000), 1, ['cheese' => 'no goat']);
    $cart->add(new FakeBuyable('1', 1000), 1, ['cheese' => 'no blue']);

    $ids = array_map(
        static fn($item): string => $item->getId(),
        $cart->items()
    );

    // Two lines of one buyable need two ids — the id is what a basket form
    // round-trips, and it must name a line, not a buyable.
    expect($ids[0])->not->toBe($ids[1]);

    // And merging back into a line keeps its id: the form printed against it
    // is still valid after the cart grows.
    $cart->add(new FakeBuyable('1', 1000), 1, ['cheese' => 'no goat']);
    expect($cart->items()[0]->getId())->toBe($ids[0]);
});
