<?php

declare(strict_types=1);

namespace Tests\Support;

use Tnt\Ecommerce\Revisions\AddUuidToOrderTable;

/**
 * The real uuid revision, stopped just short of the database.
 */
final class CapturingAddUuidToOrderTable extends AddUuidToOrderTable
{
    use CapturesRevisionStatements;
}
