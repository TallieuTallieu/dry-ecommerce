<?php

namespace Tnt\Ecommerce\Revisions;

use Oak\Contracts\Migration\RevisionInterface;
use Tnt\Dbi\TableBuilder;

class CreateCustomerTable extends DatabaseRevision implements RevisionInterface
{
    /**
     * @return void
     */
    public function up(): void
    {
        $this->queryBuilder
            ->table('ecommerce_customer')
            ->create(function (TableBuilder $table) {
                $table->addColumn('id', 'int')->length(11)->primaryKey();
                $table->addColumn('created', 'int')->length(11);
                $table->addColumn('updated', 'int')->length(11);
                $table->addColumn('first_name', 'varchar')->length(255);
                $table->addColumn('last_name', 'varchar')->length(255);
                $table->addColumn('email', 'varchar')->length(255);

                // The account this checkout was made from, or NULL for a
                // guest. Deliberately no addForeignKey(): the target table
                // belongs to dry-accounts, which may not be installed — see
                // docs/customer.md.
                $table->addColumn('user', 'int')->length(11)->null();

                // Addresses live in ecommerce_address (CreateAddressTable),
                // not here.

                $table->addColumn('company', 'varchar')->length(255);
                $table->addColumn('vat', 'varchar')->length(255);

                $table->addColumn('comments', 'varchar')->length(255);
                $table->addColumn('first_contact', 'varchar')->length(255);
            });

        $this->execute();
    }

    /**
     * @return void
     */
    public function down(): void
    {
        $this->queryBuilder->table('ecommerce_customer')->drop();
        $this->execute();
    }

    public function describeUp(): string
    {
        return 'Table ecommerce_customer created';
    }

    public function describeDown(): string
    {
        return 'Table ecommerce_customer dropped';
    }
}
