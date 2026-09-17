<?php

declare(strict_types=1);

namespace Tnt\Ecommerce\Revisions;

use Oak\Contracts\Migration\RevisionInterface;
use Tnt\Dbi\QueryBuilder;
use Tnt\Dbi\TableBuilder;

/**
 * The two indexes {@see AddIndexesToEcommerceTables} left out: "which orders
 * contain this product", and "which orders go out by this method". Both were
 * full table scans, and a shop asks the first one per product on every
 * availability calendar it builds.
 */
class AddOrderLineIndexes extends DatabaseRevision implements RevisionInterface
{
    /**
     * @return void
     */
    public function up(): void
    {
        // The buyable, then the order. `item_class` first on purpose: the
        // `order` foreign key keeps its own implicit index that way, so
        // down() needs none of the re-add dance AddIndexesToEcommerceTables
        // documents. The trailing `order` is what turns "which orders hold
        // this buyable" into an index-only read.
        $this->queryBuilder
            ->table('ecommerce_order_item')
            ->alter(function (TableBuilder $table) {
                $table->addIndex(['item_class', 'item_id', 'order']);
            });

        $this->execute();

        // One statement per builder: a QueryBuilder accumulates its query
        // text, so reusing it would concatenate the ALTERs.
        $this->queryBuilder = new QueryBuilder();

        // Not a foreign key — the column holds whatever id the shop's
        // fulfillment method answers, so an index is all it can have.
        $this->queryBuilder
            ->table('ecommerce_order')
            ->alter(function (TableBuilder $table) {
                $table->addIndex('fulfillment_method');
            });

        $this->execute();
    }

    /**
     * @return void
     */
    public function down(): void
    {
        $this->queryBuilder
            ->table('ecommerce_order')
            ->alter(function (TableBuilder $table) {
                $table->dropIndex('fulfillment_method');
            });

        $this->execute();
        $this->queryBuilder = new QueryBuilder();

        $this->queryBuilder
            ->table('ecommerce_order_item')
            ->alter(function (TableBuilder $table) {
                $table->dropIndex(['item_class', 'item_id', 'order']);
            });

        $this->execute();
    }

    public function describeUp(): string
    {
        return 'Indexes added on ecommerce_order_item (buyable) and ' .
            'ecommerce_order (fulfillment_method)';
    }

    public function describeDown(): string
    {
        return 'Indexes dropped from ecommerce_order_item and ecommerce_order';
    }
}
