<?php

namespace Tnt\Ecommerce\Revisions;

use Oak\Contracts\Migration\RevisionInterface;
use Tnt\Dbi\TableBuilder;

class CreateStockItemTable extends DatabaseRevision implements RevisionInterface
{
    public function up()
    {
        $this->queryBuilder->table('ecommerce_stock_item')->create(function(TableBuilder $table) {

            $table->addColumn('id', 'int')->length(11)->primaryKey();
            $table->addColumn('created', 'int')->length(11);
            $table->addColumn('updated', 'int')->length(11);
            $table->addColumn('stock', 'int')->length(11);
            $table->addColumn('item_id', 'int')->length(11);
            $table->addColumn('item_class', 'varchar')->length(255);
            $table->addColumn('quantity', 'int')->length(11);

            $table->addForeignKey('stock', 'ecommerce_stock', 'id');
        });

        $this->execute();
    }

    public function down()
    {
        $this->queryBuilder->table('ecommerce_stock_item')->drop();
        $this->execute();
    }

    public function describeUp(): string
    {
        return 'Table ecommerce_stock_item created';
    }

    public function describeDown(): string
    {
        return 'Table ecommerce_stock_item dropped';
    }
}