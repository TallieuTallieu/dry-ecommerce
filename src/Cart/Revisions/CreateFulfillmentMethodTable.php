<?php

namespace Tnt\Ecommerce\Revisions;

use Oak\Contracts\Migration\RevisionInterface;
use Tnt\Dbi\TableBuilder;

class CreateFulfillmentMethodTable extends DatabaseRevision implements RevisionInterface
{
    public function up()
    {
        $this->queryBuilder->table('ecommerce_fulfillment_method')->create(function(TableBuilder $table) {

            $table->addColumn('id', 'int')->length(11)->primaryKey();
            $table->addColumn('created', 'int')->length(11);
            $table->addColumn('updated', 'int')->length(11);
            $table->addColumn('title', 'varchar')->length(255);
            $table->addColumn('type', 'varchar')->length(255);
        });

        $this->execute();
    }

    public function down()
    {
        $this->queryBuilder->table('ecommerce_fulfillment_method')->drop();
        $this->execute();
    }

    public function describeUp(): string
    {
        return 'Table ecommerce_fulfillment_method created';
    }

    public function describeDown(): string
    {
        return 'Table ecommerce_fulfillment_method dropped';
    }
}