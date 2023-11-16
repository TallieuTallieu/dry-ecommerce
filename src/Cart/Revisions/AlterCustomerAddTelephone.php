<?php

namespace Tnt\Ecommerce\Revisions;

use Oak\Contracts\Migration\RevisionInterface;
use Tnt\Dbi\TableBuilder;

class AlterCustomerAddTelephone extends DatabaseRevision implements RevisionInterface
{
    public function up()
    {
        $this->queryBuilder->table('ecommerce_customer')->alter(function (TableBuilder $table) {

            $table->addColumn('telephone', 'varchar')->length(255);
        });

        $this->execute();
    }

    public function down()
    {
        $this->queryBuilder->table('ecommerce_customer')->alter(function (TableBuilder $table) {

            $table->dropColumn('telephone');
        });

        $this->execute();
    }

    public function describeUp(): string
    {
        return 'Alter ecommerce_customer added telephone';
    }

    public function describeDown(): string
    {
        return 'Alter ecommerce_customer dropped telephone';
    }
}