<?php

declare(strict_types=1);

namespace Tests\Support;

use Tnt\Ecommerce\Contracts\OrderInterface;
use Tnt\Ecommerce\Contracts\PaymentGatewayInterface;
use Tnt\Ecommerce\Contracts\RedirectorInterface;
use Tnt\Ecommerce\Model\Order;
use Tnt\Ecommerce\Payment\PaymentStatus;

/**
 * An in-memory provider on the full gateway contract: pay() writes a fresh
 * payment id onto the order and answers with a redirect to a made-up
 * checkout page; statusOf() reports whatever the test scripted, standing in
 * for the provider's API.
 */
final class FakeGateway implements PaymentGatewayInterface
{
    /**
     * How many payments were created — the counter behind the ids, so a
     * re-placed order visibly gets a fresh one.
     *
     * @var int
     */
    public int $created = 0;

    /**
     * What the "provider" currently says about each payment id.
     *
     * @var array<string, PaymentStatus>
     */
    public array $reports = [];

    /**
     * @var RedirectorInterface
     */
    private RedirectorInterface $redirector;

    /**
     * @param RedirectorInterface $redirector
     */
    public function __construct(RedirectorInterface $redirector)
    {
        $this->redirector = $redirector;
    }

    /**
     * A fresh payment every call — a re-placed order's old id is dead at the
     * provider, so it is overwritten, never kept.
     *
     * @param OrderInterface $order
     * @return void
     */
    public function pay(OrderInterface $order)
    {
        $paymentId = 'tr_fake_' . ++$this->created;

        if ($order instanceof Order) {
            $order->payment_id = $paymentId;
            $order->save();
        }

        $this->redirector->redirect(
            'https://pay.example/checkout/' . $paymentId
        );
    }

    /**
     * @param string $paymentId
     * @return PaymentStatus
     */
    public function statusOf(string $paymentId): PaymentStatus
    {
        return $this->reports[$paymentId] ?? PaymentStatus::Pending;
    }
}
