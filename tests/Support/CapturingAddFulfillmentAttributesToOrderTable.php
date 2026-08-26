<?php

declare(strict_types=1);

namespace Tests\Support;

use Tnt\Ecommerce\Revisions\AddFulfillmentAttributesToOrderTable;

/**
 * The real `fulfillment_attributes` revision, stopped just short of the
 * database.
 */
final class CapturingAddFulfillmentAttributesToOrderTable extends
    AddFulfillmentAttributesToOrderTable
{
    use CapturesRevisionSql;
}
