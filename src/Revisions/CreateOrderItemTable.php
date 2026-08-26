<?php

namespace Tnt\Ecommerce\Revisions;

use Oak\Contracts\Migration\RevisionInterface;
use Tnt\Dbi\TableBuilder;

class CreateOrderItemTable extends DatabaseRevision implements RevisionInterface
{
    /**
     * @return void
     */
    public function up(): void
    {
        $this->queryBuilder
            ->table('ecommerce_order_item')
            ->create(function (TableBuilder $table) {
                $table->addColumn('id', 'int')->length(11)->primaryKey();
                $table->addColumn('created', 'int')->length(11);
                $table->addColumn('updated', 'int')->length(11);
                $table->addColumn('order', 'int')->length(11);
                $table->addColumn('item_id', 'int')->length(11);
                $table->addColumn('item_class', 'varchar')->length(255);
                // The frozen line total, in integer cents. See
                // CreateOrderTable for why this is a bigint.
                $table->addColumn('price', 'bigint')->length(20);
                $table->addColumn('quantity', 'int')->length(11);

                $table->addForeignKey('order', 'ecommerce_order', 'id');
            });

        $this->execute();
    }

    /**
     * @return void
     */
    public function down(): void
    {
        $this->queryBuilder->table('ecommerce_order_item')->drop();
        $this->execute();
    }

    public function describeUp(): string
    {
        return 'Table ecommerce_order_item created';
    }

    public function describeDown(): string
    {
        return 'Table ecommerce_order_item dropped';
    }
}
