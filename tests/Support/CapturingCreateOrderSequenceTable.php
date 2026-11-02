<?php

declare(strict_types=1);

namespace Tests\Support;

use Tnt\Ecommerce\Revisions\CreateOrderSequenceTable;

/**
 * The real `ecommerce_order_sequence` revision, stopped just short of the
 * database.
 */
final class CapturingCreateOrderSequenceTable extends CreateOrderSequenceTable
{
    use CapturesRevisionStatements;
}
