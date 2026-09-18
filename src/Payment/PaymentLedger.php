<?php

declare(strict_types=1);

namespace Tnt\Ecommerce\Payment;

use Oak\Contracts\Dispatcher\DispatcherInterface;
use Tnt\Ecommerce\Events\Order\Paid;
use Tnt\Ecommerce\Events\Order\PaymentCanceled;
use Tnt\Ecommerce\Events\Order\PaymentExpired;
use Tnt\Ecommerce\Events\Order\PaymentFailed;
use Tnt\Ecommerce\Events\Order\PaymentPartiallyRefunded;
use Tnt\Ecommerce\Events\Order\PaymentRefunded;
use Tnt\Ecommerce\Model\Order;
use Tnt\Ecommerce\Model\PaymentEntry;

/**
 * The one writer of the payment ledger and of `payment_status`. A gateway
 * reports; this compares the report with what is written, adds only what is
 * missing, and derives the order's status from the entries. See
 * docs/payment.md.
 */
class PaymentLedger
{
    /**
     * @var DispatcherInterface
     */
    private DispatcherInterface $dispatcher;

    /**
     * @param DispatcherInterface $dispatcher
     */
    public function __construct(DispatcherInterface $dispatcher)
    {
        $this->dispatcher = $dispatcher;
    }

    /**
     * Record what pay() did for a just-placed order. The attempt becomes the
     * order's current one; a refusal leaves the order failed and re-placeable.
     *
     * @param Order $order
     * @param string $provider
     * @param PaymentOutcome $outcome
     * @return void
     */
    public function start(
        Order $order,
        string $provider,
        PaymentOutcome $outcome
    ): void {
        $paymentId = match (true) {
            $outcome instanceof PaymentRedirect => $outcome->paymentId,
            $outcome instanceof PaymentSettled => $outcome->report->paymentId,
            $outcome instanceof PaymentRefused => $outcome->paymentId,
            default => null,
        };

        if ($paymentId === null) {
            // Refused before the provider handed out an id: no attempt to
            // file anything under, so nothing is written to the ledger and
            // the column says failed on its own — as a refused attempt with
            // an id would read, unless the order is already paid in full.
            $this->settle(
                $order,
                $this->isPaidInFull($order)
                    ? PaymentStatus::Paid
                    : PaymentStatus::Failed
            );

            return;
        }

        $this->write(
            $this->entry(
                $order,
                $provider,
                $paymentId,
                EntryKind::AttemptStarted
            )
        );

        $order->payment_id = $paymentId;
        $order->save();

        if ($outcome instanceof PaymentSettled) {
            $this->apply($order, $provider, $outcome->report);

            return;
        }

        if ($outcome instanceof PaymentRefused) {
            $this->apply(
                $order,
                $provider,
                new PaymentReport($paymentId, PaymentStatus::Failed)
            );

            return;
        }

        $this->recompute($order);
    }

    /**
     * Write what a report says that the ledger does not hold yet, then
     * recompute. Applying the same report twice writes nothing the second
     * time.
     *
     * @param Order $order
     * @param string $provider
     * @param PaymentReport $report
     * @return void
     */
    public function apply(
        Order $order,
        string $provider,
        PaymentReport $report
    ): void {
        /** @var array<string, true> $written provider|kind|reference */
        $written = [];

        // An attempt starts out pending, so a pending report on a fresh
        // attempt is no change.
        $lastStatus = PaymentStatus::Pending;

        foreach ($order->getPaymentEntries() as $entry) {
            if ($entry->reference !== null) {
                $written[
                    self::key($entry->provider, $entry->kind, $entry->reference)
                ] = true;
            }

            if (
                $entry->kind === EntryKind::StatusReported->value &&
                $entry->provider === $provider &&
                $entry->payment_id === $report->paymentId
            ) {
                $lastStatus = $entry->getStatus() ?? PaymentStatus::Pending;
            }
        }

        // Reversals last, so one listed before the movement it undoes still
        // finds its counterpart.
        $movements = $report->movements;
        usort(
            $movements,
            static fn(
                Movement $a,
                Movement $b
            ): int => ($a->kind->reverses() !== null) <=>
                ($b->kind->reverses() !== null)
        );

        foreach ($movements as $movement) {
            $key = self::key(
                $provider,
                $movement->kind->value,
                $movement->reference
            );

            if (isset($written[$key])) {
                continue;
            }

            $undoes = $movement->kind->reverses();

            if (
                $undoes !== null &&
                !isset(
                    $written[
                        self::key(
                            $provider,
                            $undoes->value,
                            $movement->reference
                        )
                    ]
                )
            ) {
                continue;
            }

            $entry = $this->entry(
                $order,
                $provider,
                $report->paymentId,
                $movement->kind
            );
            $entry->amount = $movement->amount;
            $entry->reference = $movement->reference;
            $this->write($entry);

            $written[$key] = true;
        }

        if ($report->status !== $lastStatus) {
            $entry = $this->entry(
                $order,
                $provider,
                $report->paymentId,
                EntryKind::StatusReported
            );
            $entry->status = $report->status->value;
            $this->write($entry);
        }

        $this->recompute($order);
    }

    /**
     * Record a webhook about a payment id no order's attempt carries.
     *
     * @param string $provider
     * @param string $paymentId
     * @return void
     */
    public function unknown(string $provider, string $paymentId): void
    {
        $this->write(
            $this->entry(null, $provider, $paymentId, EntryKind::UnknownPayment)
        );
    }

    /**
     * Derive the order's status from its entries into `payment_status`, and
     * dispatch the matching event when it changed. See the table in
     * docs/payment.md.
     *
     * @param Order $order
     * @return void
     */
    public function recompute(Order $order): void
    {
        $this->settle($order, $this->derive($order));
    }

    /**
     * The status the entries say (D7a in docs/payment.md): paid in full
     * wins; otherwise money decides only when the current attempt captured
     * or there is none; otherwise the current attempt's last report does.
     *
     * @param Order $order
     * @return PaymentStatus
     */
    public function derive(Order $order): PaymentStatus
    {
        if ($this->isPaidInFull($order)) {
            return PaymentStatus::Paid;
        }

        $current = (string) $order->payment_id;
        $currentCaptured = false;
        $status = PaymentStatus::Pending;

        foreach ($order->getPaymentEntries() as $entry) {
            if ($current === '' || $entry->payment_id !== $current) {
                continue;
            }

            if ($entry->kind === EntryKind::Captured->value) {
                $currentCaptured = true;
            }

            if ($entry->kind === EntryKind::StatusReported->value) {
                $status = $entry->getStatus() ?? PaymentStatus::Pending;
            }
        }

        // A re-placed order awaiting payment, or a fresh attempt: the old
        // attempts' money does not describe it.
        if ($current !== '' && !$currentCaptured) {
            return $status;
        }

        if (!$order->hasCapture()) {
            return PaymentStatus::Pending;
        }

        $paid = $order->getPaid();
        $returned = $order->getReturned();

        if ($paid > 0 && $paid - $returned <= 0) {
            return PaymentStatus::Refunded;
        }

        return $returned > 0
            ? PaymentStatus::PartiallyRefunded
            : PaymentStatus::Paid;
    }

    /**
     * Something was captured and the money kept covers the frozen total — a
     * €0 capture on a free order included.
     *
     * @param Order $order
     * @return bool
     */
    private function isPaidInFull(Order $order): bool
    {
        return $order->hasCapture() && $order->getNet() >= $order->getTotal();
    }

    /**
     * Write a status onto the order, and dispatch its event only when it
     * differs from what the order said before. Pending dispatches nothing.
     *
     * @param Order $order
     * @param PaymentStatus $status
     * @return void
     */
    private function settle(Order $order, PaymentStatus $status): void
    {
        $before = $order->getPaymentStatus();

        // Also rewrites a legacy '' as 'pending' — same reading, no event.
        if ($order->payment_status !== $status->value) {
            $order->payment_status = $status->value;
            $order->save();
        }

        if ($status === $before) {
            return;
        }

        $event = match ($status) {
            PaymentStatus::Pending => null,
            PaymentStatus::Paid => Paid::class,
            PaymentStatus::Failed => PaymentFailed::class,
            PaymentStatus::Canceled => PaymentCanceled::class,
            PaymentStatus::Expired => PaymentExpired::class,
            PaymentStatus::Refunded => PaymentRefunded::class,
            PaymentStatus::PartiallyRefunded
                => PaymentPartiallyRefunded::class,
        };

        if ($event !== null) {
            $this->dispatcher->dispatch($event, new $event($order));
        }
    }

    /**
     * A fresh entry, not yet written.
     *
     * @param Order|null $order
     * @param string $provider
     * @param string $paymentId
     * @param EntryKind $kind
     * @return PaymentEntry
     */
    private function entry(
        ?Order $order,
        string $provider,
        string $paymentId,
        EntryKind $kind
    ): PaymentEntry {
        $entry = new PaymentEntry();
        $entry->created = time();
        $entry->order = $order;
        $entry->provider = $provider;
        $entry->payment_id = $paymentId;
        $entry->kind = $kind->value;

        return $entry;
    }

    /**
     * Append one entry. The only write the ledger makes, and a test seam:
     * override it to keep the ledger in memory.
     *
     * @param PaymentEntry $entry
     * @return void
     */
    protected function write(PaymentEntry $entry): void
    {
        $entry->save();
    }

    /**
     * The uniqueness key, as UNIQUE (`provider`, `kind`, `reference`).
     *
     * @param string $provider
     * @param string $kind
     * @param string $reference
     * @return string
     */
    private static function key(
        string $provider,
        string $kind,
        string $reference
    ): string {
        return $provider . "\0" . $kind . "\0" . $reference;
    }
}
