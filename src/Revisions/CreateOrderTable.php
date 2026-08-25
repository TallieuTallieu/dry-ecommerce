<?php

namespace Tnt\Ecommerce\Revisions;

use Oak\Contracts\Migration\RevisionInterface;
use Tnt\Dbi\TableBuilder;
use Tnt\Ecommerce\Address\AddressType;

class CreateOrderTable extends DatabaseRevision implements RevisionInterface
{
    public function up()
    {
        $this->queryBuilder
            ->table('ecommerce_order')
            ->create(function (TableBuilder $table) {
                $table->addColumn('id', 'int')->length(11)->primaryKey();
                $table->addColumn('created', 'int')->length(11);
                $table->addColumn('updated', 'int')->length(11);
                $table->addColumn('order_id', 'varchar')->length(255);
                $table->addColumn('payment_id', 'varchar')->length(255);
                // Money is integer cents, and bigint is the only integer
                // width that holds every value a PHP int can carry: an int(11)
                // would stop at 2,147,483,647 cents (€21,474,836.47), which is
                // narrower than the decimal(10,2) it replaces and would
                // truncate a large order rather than refuse it.
                $table->addColumn('total', 'bigint')->length(20);
                $table->addColumn('subtotal', 'bigint')->length(20);
                $table->addColumn('reduction', 'bigint')->length(20);
                $table->addColumn('fulfillment_cost', 'bigint')->length(20);
                $table->addColumn('tax', 'bigint')->length(20);

                // Which convention the amounts above were quoted under, so an
                // order can still be reprinted as it was charged after the
                // shop changes how it quotes prices. See
                // Tnt\Ecommerce\Tax\PriceConvention.
                $table->addColumn('prices', 'varchar')->length(255);
                $table->addColumn('payment_status', 'varchar')->length(255);
                $table
                    ->addColumn('fulfillment_method', 'varchar')
                    ->length(255)
                    ->null();
                $table->addColumn('discount', 'int')->length(11)->null();
                $table->addColumn('customer', 'int')->length(11);

                // Who placed the order, frozen at checkout. The foreign key
                // above answers "whose account is this on" and follows a row
                // that a shop may let its owner edit; these three answer "who
                // placed it", and nothing but a write to this order can move
                // them.
                $table->addColumn('first_name', 'varchar')->length(255);
                $table->addColumn('last_name', 'varchar')->length(255);
                $table->addColumn('email', 'varchar')->length(255);
                $table->addColumn('company', 'varchar')->length(255);
                $table->addColumn('vat', 'varchar')->length(255);

                // And where it went. Deliberately columns and deliberately not
                // a foreign key into ecommerce_address: that table is an
                // address book, a book is edited and deleted from, and an
                // invoice is a statement about the past that a mutable row
                // cannot back. See Tnt\Ecommerce\Model\Order::freezeCustomer().
                //
                // The names come from the enum that writes them, so the table
                // and the write cannot drift apart.
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
