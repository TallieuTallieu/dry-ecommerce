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

use Tests\Support\CapturingCreateCustomerTable;
use Tests\Support\CapturingCreateOrderItemTable;
use Tests\Support\CapturingCreateOrderTable;
use Tnt\Dbi\QueryBuilder;

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
})->with(['total', 'subtotal', 'reduction', 'fulfillment_cost']);

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

it('keeps the non-money columns as they were', function (): void {
    // The reason for bigint is the size of an amount in cents, so it applies to
    // money and to nothing else. A row id or a quantity widening along with it
    // would mean the change was made by search and replace.
    expect(orderTableSql())->toContain('`id` INT(11)');
    expect(orderTableSql())->toContain('`created` INT(11)');
    expect(orderItemTableSql())->toContain('`quantity` INT(11)');
    expect(orderItemTableSql())->toContain('`item_id` INT(11)');
});
