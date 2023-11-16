<?php

namespace Tnt\Ecommerce\Revisions;

use Oak\Contracts\Migration\RevisionInterface;
use Tnt\Dbi\TableBuilder;

class CreateOrderTable extends DatabaseRevision implements RevisionInterface
{
    public function up()
    {
        $this->queryBuilder->table('ecommerce_order')->create(function(TableBuilder $table) {
            $table->addColumn('id', 'int')->length(11)->primaryKey();
            $table->addColumn('created', 'int')->length(11);
            $table->addColumn('updated', 'int')->length(11);
            $table->addColumn('order_id', 'varchar')->length(255);
            $table->addColumn('payment_id', 'varchar')->length(255);
            $table->addColumn('total', 'decimal')->length('10,2');
            $table->addColumn('subtotal', 'decimal')->length('10,2');
            $table->addColumn('reduction', 'decimal')->length('10,2');
            $table->addColumn('fulfillment_cost', 'decimal')->length('10,2');
            $table->addColumn('payment_status', 'varchar')->length(255);
            $table->addColumn('fulfillment_method', 'varchar')->length(255)->null();
            $table->addColumn('discount', 'int')->length(11)->null();
            $table->addColumn('customer', 'int')->length(11);

            $table->addForeignKey('discount', 'ecommerce_discount_code');
            $table->addForeignKey('customer', 'ecommerce_customer');
        });

        $this->execute();
    }

    public function down()
    {
        $this->queryBuilder->table('ecommerce_order')->drop();
        $this->execute();
    }

    public function describeUp(): string
    {
        return 'Table ecommerce_order created';
    }

    public function describeDown(): string
    {
        return 'Table ecommerce_order dropped';
    }
}