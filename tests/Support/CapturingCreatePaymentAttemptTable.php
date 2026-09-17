<?php

declare(strict_types=1);

namespace Tests\Support;

use Tnt\Ecommerce\Revisions\CreatePaymentAttemptTable;

/**
 * The real revision, stopped just short of the database.
 *
 * @see CapturesRevisionStatements
 */
final class CapturingCreatePaymentAttemptTable extends CreatePaymentAttemptTable
{
    use CapturesRevisionStatements;
}
