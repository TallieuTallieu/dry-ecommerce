<?php

declare(strict_types=1);

namespace Tnt\Ecommerce\Revisions;

use Oak\Contracts\Migration\RevisionInterface;
use Tnt\Dbi\TableBuilder;

/**
 * One row per year: the last order number
 * {@see \Tnt\Ecommerce\Order\YearlyOrderReference} handed out. Created for
 * every shop — it stays empty unless the shop opts in. See
 * docs/orders.md#yearly-numbers.
 */
class CreateOrderSequenceTable extends DatabaseRevision implements
    RevisionInterface
{
    /**
     * @return void
     */
    public function up(): void
    {
        $this->queryBuilder
            ->table('ecommerce_order_sequence')
            ->create(function (TableBuilder $table) {
                // The four-digit year. The key the upsert collides on, so no
                // AUTO_INCREMENT.
                $table->addColumn('year', 'int')->length(4)->primaryKey(false);
                $table->addColumn('last', 'int')->length(11);
            });

        $this->execute();
    }

    /**
     * @return void
     */
    public function down(): void
    {
        $this->queryBuilder->table('ecommerce_order_sequence')->drop();
        $this->execute();
    }

    public function describeUp(): string
    {
        return 'Table ecommerce_order_sequence created';
    }

    public function describeDown(): string
    {
        return 'Table ecommerce_order_sequence dropped';
    }
}
