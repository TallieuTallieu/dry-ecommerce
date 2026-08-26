<?php

declare(strict_types=1);

namespace Tnt\Ecommerce\Revisions;

use Oak\Contracts\Migration\RevisionInterface;
use Tnt\Dbi\QueryBuilder;
use Tnt\Dbi\TableBuilder;

/**
 * Takes the recipient name off the address concept: an address is purely a
 * where, and who placed the order is already frozen on the order itself
 * (`first_name`/`last_name`, which stay). Sits at the end of the migrator
 * list on purpose — revisions are appended, never inserted. See
 * docs/addresses.md.
 */
class DropAddressNameColumns extends DatabaseRevision implements
    RevisionInterface
{
    /**
     * The name columns each table carried, by table.
     */
    private const COLUMNS = [
        'ecommerce_address' => ['first_name', 'last_name'],
        'ecommerce_order' => [
            'billing_first_name',
            'billing_last_name',
            'shipping_first_name',
            'shipping_last_name',
        ],
    ];

    /**
     * @return void
     */
    public function up(): void
    {
        foreach (self::COLUMNS as $tableName => $columns) {
            $this->queryBuilder
                ->table($tableName)
                ->alter(function (TableBuilder $table) use ($columns) {
                    foreach ($columns as $column) {
                        $table->dropColumn($column);
                    }
                });

            $this->execute();

            // One statement per builder: a QueryBuilder accumulates its query
            // text, so reusing it would concatenate the two ALTERs.
            $this->queryBuilder = new QueryBuilder();
        }
    }

    /**
     * @return void
     */
    public function down(): void
    {
        foreach (self::COLUMNS as $tableName => $columns) {
            $this->queryBuilder
                ->table($tableName)
                ->alter(function (TableBuilder $table) use ($columns) {
                    foreach ($columns as $column) {
                        $table->addColumn($column, 'varchar')->length(255);
                    }
                });

            $this->execute();

            $this->queryBuilder = new QueryBuilder();
        }
    }

    public function describeUp(): string
    {
        return 'Name columns dropped from ecommerce_address and ecommerce_order';
    }

    public function describeDown(): string
    {
        return 'Name columns re-added to ecommerce_address and ecommerce_order';
    }
}
