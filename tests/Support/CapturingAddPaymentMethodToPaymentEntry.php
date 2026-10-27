<?php

declare(strict_types=1);

namespace Tests\Support;

use Tnt\Ecommerce\Revisions\AddPaymentMethodToPaymentEntry;

/**
 * The real revision, stopped just short of the database.
 *
 * @see CapturesRevisionStatements
 */
final class CapturingAddPaymentMethodToPaymentEntry extends
    AddPaymentMethodToPaymentEntry
{
    use CapturesRevisionStatements;
}
