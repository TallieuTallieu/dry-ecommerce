<?php

namespace Tnt\Ecommerce\Revisions;

use Oak\Contracts\Migration\RevisionInterface;
use Tnt\Dbi\TableBuilder;

class CreateCartTable extends DatabaseRevision implements RevisionInterface
{
    public function up()
    {
        $this->queryBuilder->table('ecommerce_cart')->create(function (TableBuilder $table) {

            $table->addColumn('id', 'int')->length(11)->primaryKey();
            $table->addColumn('created', 'int')->length(11);
            $table->addColumn('updated', 'int')->length(11);
            $table->addColumn('fulfillment_method', 'varchar')->length(255)->null();
            $table->addColumn('discount', 'int')->length(11)->null();

            $table->addForeignKey('discount', 'ecommerce_discount_code');
        });

        $this->execute();
    }

    public function down()
    {
        $this->queryBuilder->table('ecommerce_cart')->drop();
        $this->execute();
    }

    public function describeUp(): string
    {
        return 'Table ecommerce_cart created';
    }

    public function describeDown(): string
    {
        return 'Table ecommerce_cart dropped';
    }
}