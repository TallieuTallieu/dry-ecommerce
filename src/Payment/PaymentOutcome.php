<?php

declare(strict_types=1);

namespace Tnt\Ecommerce\Payment;

/**
 * What pay() did: {@see PaymentRedirect}, {@see PaymentSettled} or
 * {@see PaymentRefused}. The package records it and does the redirect.
 */
interface PaymentOutcome {}
