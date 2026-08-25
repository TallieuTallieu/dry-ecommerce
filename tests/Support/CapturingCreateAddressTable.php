<?php

declare(strict_types=1);

namespace Tests\Support;

use Tnt\Ecommerce\Revisions\CreateAddressTable;

/**
 * The real `ecommerce_address` revision, stopped just short of the database.
 */
final class CapturingCreateAddressTable extends CreateAddressTable
{
    use CapturesRevisionSql;
}
