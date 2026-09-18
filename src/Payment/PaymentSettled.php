<?php

declare(strict_types=1);

namespace Tnt\Ecommerce\Payment;

/**
 * The payment was decided on the spot — no redirect, the report says how.
 */
final class PaymentSettled implements PaymentOutcome
{
    /**
     * @param PaymentReport $report
     */
    public function __construct(public readonly PaymentReport $report) {}
}
