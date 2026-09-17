<?php

declare(strict_types=1);

namespace Tests\Support;

use Tnt\Ecommerce\Model\PaymentAttempt;

/**
 * A payment attempt that keeps to memory instead of a row.
 *
 * Same seam as {@see InMemoryOrderItem}: a real {@see PaymentAttempt}
 * constructs and takes field values with no connection — only `save()` reaches
 * for one — so overriding it is enough to run
 * {@see \Tnt\Ecommerce\Model\Order::startPaymentAttempt()} and the whole of
 * {@see \Tnt\Ecommerce\Payment\PaymentWebhook::handle()} for real.
 */
final class InMemoryPaymentAttempt extends PaymentAttempt
{
    /**
     * How many times `save()` was called.
     *
     * @var int
     */
    public int $saveCount = 0;

    /**
     * @return mixed|void
     */
    public function save()
    {
        $this->saveCount++;
    }
}
