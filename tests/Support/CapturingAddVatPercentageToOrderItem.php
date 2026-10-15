<?php

declare(strict_types=1);

namespace Tests\Support;

use Tnt\Ecommerce\Revisions\AddVatPercentageToOrderItem;

/**
 * The real revision, stopped just short of the database.
 *
 * @see CapturesRevisionStatements
 */
final class CapturingAddVatPercentageToOrderItem extends
    AddVatPercentageToOrderItem
{
    use CapturesRevisionStatements;
}
