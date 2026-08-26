<?php

namespace Tnt\Ecommerce\Revisions;

use Oak\Contracts\Migration\RevisionInterface;
use Tnt\Dbi\TableBuilder;
use Tnt\Ecommerce\Address\AddressType;

class CreateOrderTable extends DatabaseRevision implements RevisionInterface
{
    /**
     * @return void
     */
    public function up(): void
    {
        $this->queryBuilder
            ->table('ecommerce_order')
            ->create(function (TableBuilder $table) {
                $table->addColumn('id', 'int')->length(11)->primaryKey();
                $table->addColumn('created', 'int')->length(11);
                $table->addColumn('updated', 'int')->length(11);
                $table->addColumn('order_id', 'varchar')->length(255);
                $table->addColumn('payment_id', 'varchar')->length(255);
                // Integer cents; bigint because int(11) tops out at
                // €21,474,836.47 and MySQL would truncate, not refuse.
                $table->addColumn('total', 'bigint')->length(20);
                $table->addColumn('subtotal', 'bigint')->length(20);
                $table->addColumn('reduction', 'bigint')->length(20);
                $table->addColumn('fulfillment_cost', 'bigint')->length(20);
                $table->addColumn('tax', 'bigint')->length(20);

                // Which convention the amounts above were quoted under — see
                // Tnt\Ecommerce\Tax\PriceConvention.
                $table->addColumn('prices', 'varchar')->length(255);
                $table->addColumn('payment_status', 'varchar')->length(255);
                $table
                    ->addColumn('fulfillment_method', 'varchar')
                    ->length(255)
                    ->null();
                $table->addColumn('discount', 'int')->length(11)->null();
                $table->addColumn('customer', 'int')->length(11);

                // Who placed the order, frozen at checkout.
                $table->addColumn('first_name', 'varchar')->length(255);
                $table->addColumn('last_name', 'varchar')->length(255);
                $table->addColumn('email', 'varchar')->length(255);
                $table->addColumn('company', 'varchar')->length(255);
                $table->addColumn('vat', 'varchar')->length(255);

                // And where it went — frozen columns, deliberately not a
                // foreign key into the editable address book. The names come
                // from the enum that writes them, so table and write agree.
                foreach (AddressType::cases() as $type) {
                    foreach ($type->columns() as $column) {
                        $table->addColumn($column, 'varchar')->length(255);
                    }
                }

                $table->addForeignKey('discount', 'ecommerce_discount_code');
                $table->addForeignKey('customer', 'ecommerce_customer');
            });

        $this->execute();
    }

    /**
     * @return void
     */
    public function down(): void
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
