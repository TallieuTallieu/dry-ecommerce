<?php

namespace Tnt\Ecommerce\Revisions;

use Oak\Contracts\Migration\RevisionInterface;
use Tnt\Dbi\TableBuilder;

class CreateStockTable extends DatabaseRevision implements RevisionInterface
{
    public function up()
    {
        $this->queryBuilder->table('ecommerce_stock')->create(function(TableBuilder $table) {
            $table->addColumn('id', 'int')->length(11)->primaryKey();
            $table->addColumn('created', 'int')->length(11);
            $table->addColumn('updated', 'int')->length(11);
            $table->addColumn('title', 'varchar')->length(255);
            $table->addColumn('hid', 'varchar')->length(255);
        });

        $this->execute();
    }

    public function down()
    {
        $this->queryBuilder->table('ecommerce_stock')->drop();
        $this->execute();
    }

    public function describeUp(): string
    {
        return 'Table ecommerce_stock created';
    }

    public function describeDown(): string
    {
        return 'Table ecommerce_stock dropped';
    }
}