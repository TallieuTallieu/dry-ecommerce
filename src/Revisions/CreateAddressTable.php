<?php

namespace Tnt\Ecommerce\Revisions;

use Oak\Contracts\Migration\RevisionInterface;
use Tnt\Dbi\TableBuilder;

/**
 * The customer address book. Sits at the end of the migrator list on purpose
 * — revisions are appended, never inserted. Existing shops move the old
 * inline address columns over by hand; see docs/installation.md.
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

                // Whose book this is.
                $table->addColumn('customer', 'int')->length(11);

                // What the address is for — see
                // Tnt\Ecommerce\Address\AddressType.
                $table->addColumn('type', 'varchar')->length(255);

                // The one of its type a checkout takes when the shop does not
                // name one.
                $table->addColumn('is_default', 'int')->length(1);

                // The recipient, who need not be the customer.
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
