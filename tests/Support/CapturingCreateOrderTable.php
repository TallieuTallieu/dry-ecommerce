<?php

declare(strict_types=1);

namespace Tests\Support;

use Tnt\Ecommerce\Revisions\CreateOrderTable;

/**
 * The real `ecommerce_order` revision, stopped just short of the database.
 */
final class CapturingCreateOrderTable extends CreateOrderTable
{
    use CapturesRevisionSql;
}
