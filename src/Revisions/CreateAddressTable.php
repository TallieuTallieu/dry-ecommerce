<?php

namespace Tnt\Ecommerce\Revisions;

use Oak\Contracts\Migration\RevisionInterface;
use Tnt\Dbi\TableBuilder;

/**
 * The customer address book.
 *
 * # Last in the list, on purpose
 *
 * Oak's migrator is a positional version counter: it remembers how many
 * revisions a shop has run, not which ones. Inserting this anywhere but the end
 * of {@see \Tnt\Ecommerce\EcommerceServiceProvider}'s list would shift every
 * revision after it by one, and a shop already at version 9 would then run
 * whatever now sits at position 9 — the wrong statement against a table it
 * already has.
 *
 * The corollary is the twelve address columns this replaces. They were removed
 * from {@see CreateCustomerTable} in place, because that is what this package
 * has always done with a revision an existing shop has already applied. A fresh
 * install therefore gets the new schema and an existing one does not: see the
 * upgrade note in the README for the four statements it has to run by hand.
 */
class CreateAddressTable extends DatabaseRevision implements RevisionInterface
{
    /**
     * @return void
     */
    public function up(): void
    {
        $this->queryBuilder
            ->table('ecommerce_address')
            ->create(function (TableBuilder $table) {
                $table->addColumn('id', 'int')->length(11)->primaryKey();
                $table->addColumn('created', 'int')->length(11);
                $table->addColumn('updated', 'int')->length(11);

                // Whose book this is. A real foreign key, unlike
                // ecommerce_customer.user, because the table it points at is
                // this package's own and is always there.
                $table->addColumn('customer', 'int')->length(11);

                // What the address is for: see
                // Tnt\Ecommerce\Address\AddressType. A varchar rather than a
                // MySQL enum, matching ecommerce_order.prices — adding a case
                // to a PHP enum should not need a schema change, and a value
                // this package cannot read is refused in PHP where it can say
                // what was wrong with it.
                $table->addColumn('type', 'varchar')->length(255);

                // The one of its type a checkout takes when the shop does not
                // name one. Not a foreign key on the customer pointing back
                // here: that is two columns to keep in step with this table
                // rather than one, and it lets a customer point at an address
                // belonging to somebody else.
                $table->addColumn('is_default', 'int')->length(1);

                // The recipient, who need not be the customer: a parcel can go
                // to a colleague or to whoever the gift is for.
                $table->addColumn('first_name', 'varchar')->length(255);
                $table->addColumn('last_name', 'varchar')->length(255);

                $table->addColumn('street', 'varchar')->length(255);
                $table->addColumn('number', 'varchar')->length(255);
                $table->addColumn('postal_code', 'varchar')->length(255);
                $table->addColumn('city', 'varchar')->length(255);
                $table->addColumn('country', 'varchar')->length(255);

                $table->addForeignKey('customer', 'ecommerce_customer', 'id');
            });

        $this->execute();
    }

    /**
     * @return void
     */
    public function down(): void
    {
        $this->queryBuilder->table('ecommerce_address')->drop();
        $this->execute();
    }

    public function describeUp(): string
    {
        return 'Table ecommerce_address created';
    }

    public function describeDown(): string
    {
        return 'Table ecommerce_address dropped';
    }
}
