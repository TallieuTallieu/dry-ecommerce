<?php

declare(strict_types=1);

namespace Tests\Support;

use Tnt\Ecommerce\Revisions\MakeOrderCustomerNullable;

/**
 * The real customer-nullable revision, stopped just short of the database.
 */
final class CapturingMakeOrderCustomerNullable extends MakeOrderCustomerNullable
{
    use CapturesRevisionSql;
}
