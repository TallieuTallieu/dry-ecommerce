<?php

declare(strict_types=1);

namespace Tests\Support;

use Tnt\Ecommerce\Contracts\OrderInterface;
use Tnt\Ecommerce\Contracts\PaymentInterface;
use Tnt\Ecommerce\Payment\PaymentOutcome;
use Tnt\Ecommerce\Payment\PaymentRedirect;

/**
 * A payment method that records what it was asked to pay and answers with
 * a redirect — an asynchronous gateway whose webhook has not arrived — or
 * with whatever outcome the test scripted.
 */
final class FakePayment implements PaymentInterface
{
    /**
     * @var array<int, OrderInterface>
     */
    public array $paid = [];

    /**
     * The next answer, or null for a fresh redirect.
     */
    public ?PaymentOutcome $outcome = null;

    /**
     * @return string
     */
    public function provider(): string
    {
        return 'fake';
    }

    /**
     * @param OrderInterface $order
     * @return PaymentOutcome
     */
    public function pay(OrderInterface $order): PaymentOutcome
    {
        $this->paid[] = $order;

        return $this->outcome ??
            new PaymentRedirect(
                'tr_fake_' . count($this->paid),
                'https://pay.example/checkout/' . count($this->paid)
            );
    }
}
