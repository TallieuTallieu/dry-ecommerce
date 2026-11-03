<?php

declare(strict_types=1);

namespace Tests\Support;

use Tnt\Ecommerce\Revisions\AddFulfillmentReductionToOrderTable;

/**
 * The real revision, stopped just short of the database.
 */
final class CapturingAddFulfillmentReductionToOrderTable extends
    AddFulfillmentReductionToOrderTable
{
    use CapturesRevisionStatements;
}
