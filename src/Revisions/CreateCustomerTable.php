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

                // No address columns. There used to be twelve of them — five
                // address_* and seven shipping_* — and between them they fixed
                // a customer at exactly one billing and one shipping address
                // for ever, which is not a shape an address list fits into.
                // They live in ecommerce_address now, one row per address, as
                // many as the customer has. See CreateAddressTable.

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
