<?php

declare(strict_types=1);

/*
 * What the money columns are declared as.
 *
 * Money is an int number of cents, so the columns that store it have to be an
 * integer type wide enough for one. `bigint` is that type: `int` stops at
 * 2,147,483,647 cents, which is narrower than the `decimal(10,2)` it replaced,
 * and MySQL would truncate a large order rather than refuse it. That reasoning
 * only holds while the columns stay `bigint`, and nothing enforced it — the
 * type was checked by hand against the container once and then left to the
 * next reader to notice.
 *
 * These tests build the real revisions and read the statement they would run.
 * `DatabaseRevision::execute()` is the only step that needs a database, and
 * Tests\Support\CapturesRevisionSql replaces it, so this runs with the
 * container stopped like the rest of tests/Unit.
 *
 * What is therefore covered here is the declaration, not the round trip. That
 * MySQL 8.0.34 gives back the full signed 64-bit range through a bigint column
 * is a property of MySQL rather than of this package, and testing it needs a
 * booted Dry application and a live connection, which the suite does not have.
 */

use Tests\Support\CapturingAddCartLifecycleColumns;
use Tests\Support\CapturingAddFulfillmentAttributesToOrderTable;
use Tests\Support\CapturingAddOptionsToLineTables;
use Tests\Support\CapturingAddOrderStateColumn;
use Tests\Support\CapturingCreateAddressTable;
use Tests\Support\CapturingDropAddressNameColumns;
use Tests\Support\CapturingCreateCustomerTable;
use Tests\Support\CapturingCreateOrderItemTable;
use Tests\Support\CapturingCreateOrderTable;
use Tests\Support\CapturingMakeOrderCustomerNullable;
use Tnt\Dbi\QueryBuilder;
use Tnt\Ecommerce\Address\AddressType;

/**
 * @return string
 */
function orderTableSql(): string
{
    $revision = new CapturingCreateOrderTable(new QueryBuilder());
    $revision->up();

    return $revision->sql;
}

/**
 * @return string
 */
function orderItemTableSql(): string
{
    $revision = new CapturingCreateOrderItemTable(new QueryBuilder());
    $revision->up();

    return $revision->sql;
}

/**
 * @return string
 */
function customerTableSql(): string
{
    $revision = new CapturingCreateCustomerTable(new QueryBuilder());
    $revision->up();

    return $revision->sql;
}

/**
 * @return string
 */
function addressTableSql(): string
{
    $revision = new CapturingCreateAddressTable(new QueryBuilder());
    $revision->up();

    return $revision->sql;
}

it('declares the customer account column as a nullable int', function (): void {
    // Nullable because guest checkout is a first-class path: a customer with no
    // account is the ordinary case, not a broken row.
    expect(customerTableSql())->toContain('`user` INT(11) NULL');
});

it('puts no foreign key on the customer account column', function (): void {
    // The one relation in this package with no database-level constraint behind
    // it, and the reason is that the table it would point at belongs to
    // dry-accounts — a supported pairing, not a dependency. A shop selling
    // without accounts has no such table, and MySQL refuses a constraint
    // against a table that is not there, so declaring one would break the
    // migrator on exactly the shops the nullable column exists to keep working.
    //
    // Asserted on the whole statement rather than on the column, because any
    // FOREIGN KEY appearing here at all would be the mistake.
    expect(customerTableSql())->not->toContain('FOREIGN KEY');
});

it('still constrains the relations it does own', function (): void {
    // The corollary, so the test above cannot be satisfied by dropping foreign
    // keys everywhere. An order's customer is this package's own table and
    // keeps a real key.
    expect(orderTableSql())->toContain('FOREIGN KEY');
    expect(orderTableSql())->toContain('REFERENCES `ecommerce_customer`');
});

it('declares every order money column as a bigint', function (
    string $column
): void {
    expect(orderTableSql())->toContain('`' . $column . '` BIGINT(20)');
})->with(['total', 'subtotal', 'reduction', 'fulfillment_cost', 'tax']);

it('keeps the business identity on the account', function (
    string $column
): void {
    // Company name and VAT number together, on the customer rather than on an
    // address: an account can be opened in the name of a business, and that is
    // one identity rather than two facts that travel together.
    expect(customerTableSql())->toContain('`' . $column . '` VARCHAR(255)');
})->with(['company', 'vat']);

it('marks which address a checkout takes by default', function (): void {
    // The mark lives on the address rather than as two columns on the
    // customer pointing back at it: one place to keep in step instead of two,
    // and no way for a customer to point at somebody else's address.
    expect(addressTableSql())->toContain('`is_default` INT(1)');
});

it('records the convention an order was priced under', function (): void {
    // Not a money column, and not derivable from the money columns either:
    // whether a total is gross or net cannot be recovered from the figures.
    expect(orderTableSql())->toContain('`prices` VARCHAR(255)');
});

it('declares the order line price as a bigint', function (): void {
    expect(orderItemTableSql())->toContain('`price` BIGINT(20)');
});

it('leaves no decimal column on either money table', function (): void {
    // The type these columns were before cents. A money column that goes back
    // to decimal takes the fractional part of a cent with it, so it is worth
    // failing on the word itself rather than on one column name.
    expect(orderTableSql())->not->toContain('DECIMAL');
    expect(orderItemTableSql())->not->toContain('DECIMAL');
});

it('gives the address book a column for each field', function (
    string $column
): void {
    expect(addressTableSql())->toContain('`' . $column . '` VARCHAR(255)');
})->with(['type', 'street', 'number', 'postal_code', 'city', 'country']);

it('ties every address to the customer whose book it is', function (): void {
    // A real foreign key, unlike ecommerce_customer.user: the table it points
    // at is this package's own and is always there.
    expect(addressTableSql())->toContain('`customer` INT(11)');
    expect(addressTableSql())->toContain('REFERENCES `ecommerce_customer`');
});

it('has taken the address columns off the customer', function (
    string $column
): void {
    // Ten columns that fixed a customer at one billing and one shipping
    // address for ever. Their replacement is ecommerce_address, and leaving
    // these behind would leave two places to write an address to and no way to
    // tell which one a shop had used.
    expect(customerTableSql())->not->toContain('`' . $column . '`');
})->with([
    'address_street',
    'address_number',
    'address_postal_code',
    'address_city',
    'address_country',
    'shipping_first_name',
    'shipping_last_name',
    'shipping_street',
    'shipping_number',
    'shipping_postal_code',
    'shipping_city',
    'shipping_country',
]);

it('freezes an address onto the order as columns', function (
    string $column
): void {
    // Read off the enum that writes them, so this fails if the two ever stop
    // agreeing rather than passing against a hand-copied list.
    expect(orderTableSql())->toContain('`' . $column . '` VARCHAR(255)');
})->with(
    array_merge(
        AddressType::Billing->columns(),
        AddressType::Shipping->columns()
    )
);

it('records the identity the order was placed with', function (
    string $column
): void {
    expect(orderTableSql())->toContain('`' . $column . '` VARCHAR(255)');
})->with(['first_name', 'last_name', 'email', 'company', 'vat']);

it('has taken the recipient name off the address concept', function (): void {
    // sc-11257: an address is purely a where. Who placed the order is frozen
    // once, on the order itself — the name columns on the address book and on
    // the frozen blocks said it twice more, for a recipient concept no shop
    // shipping today uses. One ALTER per table, address book first.
    $revision = new CapturingDropAddressNameColumns(new QueryBuilder());
    $revision->up();

    expect($revision->statements)->toHaveCount(2);
    expect($revision->statements[0])->toContain(
        'ALTER TABLE `ecommerce_address`'
    );
    expect($revision->statements[0])->toContain('DROP COLUMN `first_name`');
    expect($revision->statements[0])->toContain('DROP COLUMN `last_name`');

    expect($revision->statements[1])->toContain(
        'ALTER TABLE `ecommerce_order`'
    );

    foreach (
        [
            'billing_first_name',
            'billing_last_name',
            'shipping_first_name',
            'shipping_last_name',
        ]
        as $column
    ) {
        expect($revision->statements[1])->toContain(
            'DROP COLUMN `' . $column . '`'
        );
    }
});

it('still creates the name columns the drop relies on', function (
    string $column
): void {
    // Pinned history: the create-table revisions run before the drop on a
    // fresh install, so they must keep building the table as it stood when
    // shops first ran them — or the drop finds nothing to drop and the
    // migrator halts.
    expect(addressTableSql())->toContain('`' . $column . '` VARCHAR(255)');
    expect(orderTableSql())->toContain(
        '`billing_' . $column . '` VARCHAR(255)'
    );
    expect(orderTableSql())->toContain(
        '`shipping_' . $column . '` VARCHAR(255)'
    );
})->with(['first_name', 'last_name']);

it('never points an order at the address book', function (): void {
    // The whole of sc-11172 in one assertion. A foreign key here would make
    // the order read through to a row the customer can edit and delete, and an
    // invoice cannot be backed by a mutable row.
    expect(orderTableSql())->not->toContain('`ecommerce_address`');
});

it(
    'adds the fulfillment attributes as a nullable text column',
    function (): void {
        // An appended revision, so it alters the existing table rather than being
        // folded into CreateOrderTable: Oak's migrator counts revisions, and a
        // shop that has already run the create must reach this schema through an
        // ALTER at the end of the list. Nullable text, because which attributes a
        // method requires is the method's own business — the schema cannot hold
        // columns it has never heard of, and an order whose method asked nothing
        // has nothing to hold.
        $revision = new CapturingAddFulfillmentAttributesToOrderTable(
            new QueryBuilder()
        );
        $revision->up();

        expect($revision->sql)->toContain('ALTER TABLE `ecommerce_order`');
        expect($revision->sql)->toContain('`fulfillment_attributes` TEXT NULL');
    }
);

it('adds the options column to both line tables', function (): void {
    // One revision, two ALTERs, because the column means the same thing on
    // both tables and neither is any use without the other: the cart line
    // carries the selection, checkout copies it onto the order line. Nullable
    // text like fulfillment_attributes, and for the same reason — which
    // options a shop offers is its own business, and NULL is what every
    // pre-options line already holds, so old lines and new no-options lines
    // read the same.
    $revision = new CapturingAddOptionsToLineTables(new QueryBuilder());
    $revision->up();

    expect($revision->statements)->toHaveCount(2);
    expect($revision->statements[0])->toContain(
        'ALTER TABLE `ecommerce_cart_item`'
    );
    expect($revision->statements[1])->toContain(
        'ALTER TABLE `ecommerce_order_item`'
    );

    foreach ($revision->statements as $statement) {
        expect($statement)->toContain(
            '`options` TEXT COLLATE utf8mb4_bin NULL'
        );
    }
});

it('compares the options column exactly, not by collation', function (): void {
    // The column is half the merge key: CartItemRepository::forBuyable() finds
    // a line by comparing it to the canonical string. dry-dbi builds tables
    // COLLATE utf8mb4_0900_ai_ci, under which MySQL reads `No Goat` = `no goat`
    // and `crème` = `creme` — so a second selection would merge into the first
    // line and be silently discarded, keeping the first line's stored JSON.
    // Verified against MySQL 8.0.34: ai_ci answers 1 to both comparisons,
    // utf8mb4_bin answers 0.
    //
    // Asserted on the collation alone rather than the whole column, so this
    // fails on it going missing however the rest of the declaration is spelled.
    $revision = new CapturingAddOptionsToLineTables(new QueryBuilder());
    $revision->up();

    foreach ($revision->statements as $statement) {
        expect($statement)->toContain('COLLATE utf8mb4_bin');
    }
});

it(
    'makes the order customer column nullable and keeps the key',
    function (): void {
        // sc-11260: a guest order has no customer row — null is the guest, not a
        // throwaway row. One CHANGE, and deliberately no touch on the foreign
        // key: a non-null value must still name a real customer.
        $revision = new CapturingMakeOrderCustomerNullable(new QueryBuilder());
        $revision->up();

        expect($revision->sql)->toContain('ALTER TABLE `ecommerce_order`');
        expect($revision->sql)->toContain(
            'CHANGE `customer` `customer` INT(11) NULL'
        );
        expect($revision->sql)->not->toContain('FOREIGN KEY');
    }
);

it(
    'adds the state column and backfills existing orders placed',
    function (): void {
        // Two statements through two seams: the ALTER through the builder, the
        // backfill as raw SQL — the builder speaks DDL, not UPDATE. The default
        // '' is what rows written by pre-state code get, and '' reads as placed,
        // which is true of every order such code writes.
        $revision = new CapturingAddOrderStateColumn(new QueryBuilder());
        $revision->up();

        expect($revision->statements)->toHaveCount(2);
        expect($revision->statements[0])->toContain(
            'ALTER TABLE `ecommerce_order`'
        );
        expect($revision->statements[0])->toContain(
            "`state` VARCHAR(255) NOT NULL DEFAULT ''"
        );
        expect($revision->statements[1])->toBe(
            "UPDATE `ecommerce_order` SET `state` = 'placed'"
        );
    }
);

it('gives the cart its afterlife columns', function (): void {
    // One revision, four columns that only mean something together: the
    // order link (provenance), the token (what the cookie holds), the
    // soft-delete mark, and the attribute bag that moved off the session.
    $revision = new CapturingAddCartLifecycleColumns(new QueryBuilder());
    $revision->up();

    expect($revision->sql)->toContain('ALTER TABLE `ecommerce_cart`');
    expect($revision->sql)->toContain('ADD `order` INT(11) NULL');
    expect($revision->sql)->toContain('ADD `token` VARCHAR(64) NULL');
    expect($revision->sql)->toContain('ADD `deleted` INT(11) NULL');
    expect($revision->sql)->toContain('ADD `fulfillment_attributes` TEXT NULL');
});

it('lets a reaped order leave its cart standing', function (): void {
    // ON DELETE SET NULL, not CASCADE: deleting an order (the draft reaper)
    // must not take a living basket with it — and not RESTRICT either, or
    // the reaper could never delete a linked draft at all.
    $revision = new CapturingAddCartLifecycleColumns(new QueryBuilder());
    $revision->up();

    expect($revision->sql)->toContain('REFERENCES `ecommerce_order`');
    expect($revision->sql)->toContain('ON DELETE SET NULL');
});

it('lets a token name at most one cart', function (): void {
    // Unique, because the token is the cart's whole identity to a cookie;
    // nullable stays possible — every pre-revision cart has none, and MySQL
    // uniques ignore NULLs.
    $revision = new CapturingAddCartLifecycleColumns(new QueryBuilder());
    $revision->up();

    expect($revision->sql)->toContain('UNIQUE (`token`)');
});

it('keeps the non-money columns as they were', function (): void {
    // The reason for bigint is the size of an amount in cents, so it applies to
    // money and to nothing else. A row id or a quantity widening along with it
    // would mean the change was made by search and replace.
    expect(orderTableSql())->toContain('`id` INT(11)');
    expect(orderTableSql())->toContain('`created` INT(11)');
    expect(orderItemTableSql())->toContain('`quantity` INT(11)');
    expect(orderItemTableSql())->toContain('`item_id` INT(11)');
});
