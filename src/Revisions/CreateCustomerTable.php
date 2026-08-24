<?php

namespace Tnt\Ecommerce\Revisions;

use Oak\Contracts\Migration\RevisionInterface;
use Tnt\Dbi\TableBuilder;

class CreateCustomerTable extends DatabaseRevision implements RevisionInterface
{
    public function up()
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
                // guest. Nullable because guest checkout is a first-class
                // path, not a degraded one.
                //
                // No addForeignKey(), unlike every other relation in this
                // package. The table it would point at belongs to
                // dry-accounts, which is a supported pairing and not a
                // dependency: a shop that sells without accounts has no such
                // table, and MySQL refuses a constraint against a table that
                // is not there. Declaring one would break the ecommerce
                // migrator on exactly the shops this nullable column exists to
                // keep working. Even where dry-accounts *is* installed the two
                // packages register separate migrators with no ordering
                // between them, so there is no point at which the target is
                // known to exist. The column is an honest int the shop's own
                // schema can constrain if it wants to.
                $table->addColumn('user', 'int')->length(11)->null();

                // Address fields
                $table->addColumn('address_street', 'varchar')->length(255);
                $table->addColumn('address_number', 'varchar')->length(255);
                $table
                    ->addColumn('address_postal_code', 'varchar')
                    ->length(255);
                $table->addColumn('address_city', 'varchar')->length(255);
                $table->addColumn('address_country', 'varchar')->length(255);

                // Shipping fields
                $table
                    ->addColumn('shipping_first_name', 'varchar')
                    ->length(255);
                $table->addColumn('shipping_last_name', 'varchar')->length(255);
                $table->addColumn('shipping_street', 'varchar')->length(255);
                $table->addColumn('shipping_number', 'varchar')->length(255);
                $table
                    ->addColumn('shipping_postal_code', 'varchar')
                    ->length(255);
                $table->addColumn('shipping_city', 'varchar')->length(255);
                $table->addColumn('shipping_country', 'varchar')->length(255);

                $table->addColumn('vat', 'varchar')->length(255);

                $table->addColumn('comments', 'varchar')->length(255);
                $table->addColumn('first_contact', 'varchar')->length(255);
            });

        $this->execute();
    }

    public function down()
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
