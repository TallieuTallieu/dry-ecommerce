<?php

declare(strict_types=1);

namespace Tnt\Ecommerce\Revisions;

use Oak\Contracts\Migration\RevisionInterface;
use Tnt\Dbi\QueryBuilder;
use Tnt\Dbi\TableBuilder;

/**
 * A slot for the bus number, on the address book and on both frozen blocks of
 * the order. Kept apart from the house number, which is the building: in
 * Belgium an address missing its bus is frequently undeliverable, and a host
 * that added the column itself had an address object that could not show it.
 *
 * Every name is spelled out rather than read off {@see
 * \Tnt\Ecommerce\Address\AddressType}, for the reason CreateOrderTable gives:
 * a revision that reads live code produces different DDL whenever that code
 * moves, and Oak's migrator replays revisions by position.
 */
class AddBoxToAddresses extends DatabaseRevision implements RevisionInterface
{
    /**
     * Table to the columns it gains — the address book holds one address, the
     * order holds two frozen copies.
     *
     * @var array<string, array<int, string>>
     */
    private const COLUMNS = [
        'ecommerce_address' => ['box'],
        'ecommerce_order' => ['billing_box', 'shipping_box'],
    ];

    /**
     * @return void
     */
    public function up(): void
    {
        foreach (self::COLUMNS as $table => $columns) {
            $this->queryBuilder
                ->table($table)
                ->alter(function (TableBuilder $table) use ($columns) {
                    foreach ($columns as $column) {
                        // Same shape as every other address column: a
                        // varchar(255) holding '' when the shop never asked.
                        $table
                            ->addColumn($column, 'varchar')
                            ->length(255)
                            ->default('');
                    }
                });

            $this->execute();

            // One statement per builder: a QueryBuilder accumulates its query
            // text, so reusing it would concatenate the ALTERs.
            $this->queryBuilder = new QueryBuilder();
        }
    }

    /**
     * @return void
     */
    public function down(): void
    {
        foreach (self::COLUMNS as $table => $columns) {
            $this->queryBuilder
                ->table($table)
                ->alter(function (TableBuilder $table) use ($columns) {
                    foreach ($columns as $column) {
                        $table->dropColumn($column);
                    }
                });

            $this->execute();

            $this->queryBuilder = new QueryBuilder();
        }
    }

    public function describeUp(): string
    {
        return 'Column box added to ecommerce_address, and billing_box and ' .
            'shipping_box to ecommerce_order';
    }

    public function describeDown(): string
    {
        return 'Column box dropped from ecommerce_address, and billing_box ' .
            'and shipping_box from ecommerce_order';
    }
}
