<?php

namespace Tnt\Ecommerce\Revisions;

use Oak\Contracts\Migration\RevisionInterface;
use Tnt\Dbi\TableBuilder;

class CreateDiscountCodeTable extends DatabaseRevision implements RevisionInterface
{
    public function up()
    {
        $this->queryBuilder->table('ecommerce_discount_code')->create(function(TableBuilder $table) {

            $table->addColumn('id', 'int')->length(11)->primaryKey();
            $table->addColumn('created', 'int')->length(11);
            $table->addColumn('updated', 'int')->length(11);
            $table->addColumn('coupon_id', 'int')->length(11);
            $table->addColumn('coupon_class', 'varchar')->length(255);
            $table->addColumn('code', 'varchar')->length(255);
        });
        
        $this->execute();
    }

    public function down()
    {
        $this->queryBuilder->table('ecommerce_discount_code')->drop();
        $this->execute();
    }

    public function describeUp(): string
    {
        return 'Table ecommerce_discount_code created';
    }

    public function describeDown(): string
    {
        return 'Table ecommerce_discount_code dropped';
    }
}