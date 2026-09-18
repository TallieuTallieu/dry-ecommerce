<?php

declare(strict_types=1);

namespace Tests\Support;

use Tnt\Ecommerce\Contracts\OrderInterface;
use Tnt\Ecommerce\Contracts\PaymentGatewayInterface;
use Tnt\Ecommerce\Payment\PaymentOutcome;
use Tnt\Ecommerce\Payment\PaymentRedirect;
use Tnt\Ecommerce\Payment\PaymentReport;
use Tnt\Ecommerce\Payment\PaymentStatus;

/**
 * An in-memory provider on the full gateway contract: pay() creates a fresh
 * payment and answers with a redirect to a made-up checkout page;
 * reportOf() answers whatever the test scripted, standing in for the
 * provider's API.
 */
final class FakeGateway implements PaymentGatewayInterface
{
    /**
     * How many payments were created — the counter behind the ids.
     *
     * @var int
     */
    public int $created = 0;

    /**
     * What the "provider" currently says about each payment id.
     *
     * @var array<string, PaymentReport>
     */
    public array $reports = [];

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
        $paymentId = 'tr_fake_' . ++$this->created;

        return new PaymentRedirect(
            $paymentId,
            'https://pay.example/checkout/' . $paymentId
        );
    }

    /**
     * @param string $paymentId
     * @return PaymentReport
     */
    public function reportOf(string $paymentId): PaymentReport
    {
        return $this->reports[$paymentId] ??
            new PaymentReport($paymentId, PaymentStatus::Pending);
    }
}
