<?php

declare(strict_types=1);

namespace Tests\Support;

use Tnt\Ecommerce\Revisions\CreateCustomerTable;

/**
 * The real `ecommerce_customer` revision, stopped just short of the database.
 */
final class CapturingCreateCustomerTable extends CreateCustomerTable
{
    use CapturesRevisionSql;
}
