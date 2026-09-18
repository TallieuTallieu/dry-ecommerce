<?php

declare(strict_types=1);

namespace Tests\Support;

use Tnt\Ecommerce\Revisions\CreatePaymentEntryTable;

/**
 * The real `ecommerce_payment_entry` revision, stopped just short of the
 * database.
 */
final class CapturingCreatePaymentEntryTable extends CreatePaymentEntryTable
{
    use CapturesRevisionStatements;
}
