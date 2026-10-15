<?php

declare(strict_types=1);

/*
 * The order line: what Order::add() copies, and what the line reads back.
 *
 * Order::add() copies six values from a cart line onto a row by hand, and
 * until the newOrderItem() seam existed nothing checked the copying itself:
 * the checkout tests override add() wholesale and keep the cart lines, so a
 * transposed pair of columns here would have passed every one of them (the
 * docblock on Tests\Support\InMemoryOrder said as much). InMemoryLineOrder
 * closes that gap the same way InMemoryOrderCart closed it one level up — one
 * overridden seam, the production body running for real.
 *
 * The reading half is OrderItemInterface's new surface: getPrice() and
 * getOptions() answer off the row's own frozen columns, no buyable and no
 * query anywhere near them, which is what lets an order line be rendered
 * without a downcast to the concrete model.
 */

use Tests\Support\FakeBuyable;
use Tests\Support\FakeTaxableBuyable;
use Tests\Support\InMemoryLineOrder;
use Tests\Support\InMemoryOrderItem;
use Tests\Support\PercentageTaxRate;
use Tnt\Ecommerce\Cart\InMemoryCartItem;
use Tnt\Ecommerce\Cart\LineOptions;

it('copies every column of a cart line onto the order line', function (): void {
    $order = new InMemoryLineOrder();
    $options = ['cheese' => 'no goat', 'size' => 'L'];

    $order->add(
        new InMemoryCartItem('7', new FakeBuyable('42', 1250), 3, $options)
    );

    expect($order->writtenLines)->toHaveCount(1);

    $line = $order->writtenLines[0];

    // Six distinct values, none equal to another, so a transposition cannot
    // pass — the same shape as the checkout money test, one level down.
    expect($line->quantity)->toBe(3);
    expect($line->price)->toBe(3750);
    expect($line->item_id)->toBe(42);
    expect($line->item_class)->toBe(FakeBuyable::class);
    expect($line->options)->toBe(LineOptions::canonical($options));
    expect($line->saveCount)->toBe(1);
});

it('freezes the options in canonical form', function (): void {
    $order = new InMemoryLineOrder();

    // However the cart line held its selection, the order's copy is the
    // canonical string — the same bytes the cart line was keyed on, so an
    // order line and the cart line it came from can never disagree about what
    // was chosen.
    $order->add(
        new InMemoryCartItem('1', new FakeBuyable('1', 1000), 1, [
            'size' => 'L',
            'gift' => true,
        ])
    );

    expect($order->writtenLines[0]->options)->toBe(
        LineOptions::canonical(['gift' => true, 'size' => 'L'])
    );
});

it('freezes a line without options as NULL', function (): void {
    $order = new InMemoryLineOrder();

    $order->add(new InMemoryCartItem('1', new FakeBuyable('1', 1000), 2));

    // NULL, not '[]' — the same spelling of "no choices" every pre-options
    // order line already holds.
    expect($order->writtenLines[0]->options)->toBeNull();
});

it('reads the frozen line back through the contract', function (): void {
    $order = new InMemoryLineOrder();
    $options = ['cheese' => 'no goat'];

    $order->add(
        new InMemoryCartItem('7', new FakeBuyable('42', 1250), 3, $options)
    );

    $line = $order->writtenLines[0];

    // The round trip a shop actually makes: checkout freezes, an order page
    // reads. Both new contract methods answer off the row, decoded.
    expect($line->getPrice())->toBe(3750);
    expect($line->getQuantity())->toBe(3);
    expect($line->getOptions())->toBe($options);
});

it('answers about the frozen line without a database', function (): void {
    // The point of getPrice() being frozen: no buyable is loaded to answer
    // it, so a line whose product has been repriced — or deleted — still
    // reads back as it was placed. This line has never been near a
    // connection, which is the proof.
    $line = new InMemoryOrderItem();
    $line->quantity = 2;
    $line->price = 4000;
    $line->options = LineOptions::canonical(['gift' => true]);

    expect($line->getPrice())->toBe(4000);
    expect($line->getOptions())->toBe(['gift' => true]);
});

it('reads a line from before options existed as none', function (): void {
    $line = new InMemoryOrderItem();
    $line->quantity = 1;
    $line->price = 1000;

    // The column is NULL on every order line placed before the revision ran,
    // and [] is the honest reading: nothing was chosen.
    expect($line->getOptions())->toBe([]);
});

it('stamps created and updated on the order line', function (): void {
    // ecommerce_order_item.created/updated are NOT NULL with no default, so
    // under MySQL strict mode a line without them is an insert error — and
    // every other insert in this package stamps them by hand.
    $order = new InMemoryLineOrder();
    $before = time();

    $order->add(new InMemoryCartItem('1', new FakeBuyable('1', 1000), 2));

    $line = $order->writtenLines[0];
    expect($line->created)->toBeGreaterThanOrEqual($before);
    expect($line->updated)->toBeGreaterThanOrEqual($before);
});

it(
    'canonicalises in-memory options like the database storage does',
    function (): void {
        // The two storages must be indistinguishable through the contract: keys
        // read back sorted from both, exactly as docs/options.md promises.
        $item = new InMemoryCartItem('1', new FakeBuyable('1', 1000), 1, [
            'size' => 'L',
            'gift' => true,
        ]);

        expect(array_keys($item->getOptions()))->toBe(['gift', 'size']);
    }
);

it('freezes the tax rate the line was sold at', function (): void {
    $order = new InMemoryLineOrder();

    $order->add(
        new InMemoryCartItem(
            '1',
            new FakeTaxableBuyable('1', 1000, new PercentageTaxRate(21)),
            1
        )
    );
    $order->add(
        new InMemoryCartItem(
            '2',
            new FakeTaxableBuyable('2', 1000, new PercentageTaxRate(5.5)),
            1
        )
    );

    expect($order->writtenLines[0]->getVatPercentage())->toBe(21.0);
    expect($order->writtenLines[1]->getVatPercentage())->toBe(5.5);
});

it('leaves the rate NULL for a buyable without tax', function (): void {
    $order = new InMemoryLineOrder();

    $order->add(new InMemoryCartItem('1', new FakeBuyable('1', 1000), 1));

    expect($order->writtenLines[0]->vat_percentage)->toBeNull();
    expect($order->writtenLines[0]->getVatPercentage())->toBeNull();
});

it('reads the stored rate back as a number', function (): void {
    // MySQL hands a DECIMAL column back as a string; NULL is every line
    // placed before the column existed.
    $line = new InMemoryOrderItem();
    $line->vat_percentage = '21.0000';

    expect($line->getVatPercentage())->toBe(21.0);

    $line->vat_percentage = null;

    expect($line->getVatPercentage())->toBeNull();
});
