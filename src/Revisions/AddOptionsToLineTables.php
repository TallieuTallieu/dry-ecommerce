<?php

declare(strict_types=1);

namespace Tnt\Ecommerce\Revisions;

use Oak\Contracts\Migration\RevisionInterface;
use Tnt\Dbi\QueryBuilder;
use Tnt\Dbi\TableBuilder;

/**
 * A slot for the choices a line was added with, on both line tables:
 * {@see \Tnt\Ecommerce\Cart\LineOptions} canonical JSON, or NULL. Sits last
 * in the migrator list on purpose — revisions are appended, never inserted.
 */
class AddOptionsToLineTables extends DatabaseRevision implements
    RevisionInterface
{
    private const TABLES = ['ecommerce_cart_item', 'ecommerce_order_item'];

    /**
     * @return void
     */
    public function up(): void
    {
        foreach (self::TABLES as $table) {
            $this->queryBuilder
                ->table($table)
                ->alter(function (TableBuilder $table) {
                    $table->addColumn('options', 'text')->null();
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
        foreach (self::TABLES as $table) {
            $this->queryBuilder
                ->table($table)
                ->alter(function (TableBuilder $table) {
                    $table->dropColumn('options');
                });

            $this->execute();

            $this->queryBuilder = new QueryBuilder();
        }
    }

    public function describeUp(): string
    {
        return 'Column options added to ecommerce_cart_item and ecommerce_order_item';
    }

    public function describeDown(): string
    {
        return 'Column options dropped from ecommerce_cart_item and ecommerce_order_item';
    }
}
