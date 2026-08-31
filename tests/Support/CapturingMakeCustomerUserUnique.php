<?php

declare(strict_types=1);

namespace Tests\Support;

use Tnt\Ecommerce\Revisions\MakeCustomerUserUnique;

/**
 * The unique-customer-user revision, stopped just short of the database.
 * One ALTER each way, so {@see CapturesRevisionSql}'s single `$sql` holds it.
 */
final class CapturingMakeCustomerUserUnique extends MakeCustomerUserUnique
{
    use CapturesRevisionSql;
}
