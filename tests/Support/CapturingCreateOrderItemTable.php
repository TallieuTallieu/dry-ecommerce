<?php

declare(strict_types=1);

namespace Tests\Support;

use Tnt\Ecommerce\Revisions\CreateOrderItemTable;

/**
 * The real `ecommerce_order_item` revision, stopped just short of the database.
 */
final class CapturingCreateOrderItemTable extends CreateOrderItemTable
{
    use CapturesRevisionSql;
}
