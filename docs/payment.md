# Payment

A gateway **reports**; the package **writes**. Every payment attempt, every
status a provider reports and every cent that moves goes into an append-only
payment ledger, `ecommerce_payment_entry`. The ledger is the order's payment
history, and it is where the order's money figures and its `payment_status`
come from. No gateway writes `payment_status`, `payment_id` or a ledger entry,
and none redirects the visitor itself.

```php
interface PaymentInterface
{
    public function provider(): string;
    public function pay(OrderInterface $order): PaymentOutcome;
}
```

`provider()` is the name every ledger entry is filed under — stable and
lowercase, e.g. `mollie`. Renaming it orphans the entries already written.
Lookups and uniqueness are per provider. Running two gateways side by side is
not supported yet.

`Cart::checkout()` — and `Cart::place()`, the
[place-step](orders.md#the-place-step) it is one call into — calls `pay()`
once, last, after the order and its lines are written and `Created` has been
dispatched. So a gateway always receives an order that already exists and has
a reference. A [re-placement](orders.md#re-placement) calls it again, which is
a fresh attempt to pay the same order.

## Choosing one

```php
// config/ecommerce.php
'payment' => \Tnt\Ecommerce\Payment\NullPayment::class,
```

It is resolved through the container, so a gateway can constructor-inject
whatever it needs.

> ### `NullPayment` is the default and it charges nobody
>
> It is the shipped dummy gateway. It behaves exactly like a real gateway whose
> every payment succeeds on the spot, minus the money. `pay()` answers
> `PaymentSettled` with a capture of the order total, under a payment id it
> mints per placement. The ledger records it and the order reads `paid`.
> `Paid` fires, single-use coupons are redeemed, and no money moves. Nothing
> anywhere warns about it.
>
> It is the right default for a package that cannot know which gateway a shop
> uses, and it makes a checkout exercisable end to end before payment is wired
> up. It is also a live risk in any project that has not got round to payment
> yet. Set a real gateway (e.g. `dry-mollie`) before launch.

## What pay() answers

`pay()` says what it did, as one of three `PaymentOutcome` value objects in
`Tnt\Ecommerce\Payment`. `Cart::place()` hands the outcome to
`PaymentLedger::start()` and then acts on it:

| Outcome                                  | Meaning                                                   | What the package does                                                                                                                                                    |
| ---------------------------------------- | --------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| `PaymentRedirect($paymentId, $checkoutUrl)` | The provider created a payment; the visitor must pay it.  | Writes `attempt_started`, points `payment_id` at it, and redirects through `RedirectorInterface`: a `302` that exits.                                                        |
| `PaymentSettled(PaymentReport)`          | Decided on the spot (`NullPayment`, a saved card).        | Writes `attempt_started`, points `payment_id` at it, applies the report. **No redirect**: `place()` returns the order and the project's controller sends the visitor on. |
| `PaymentRefused(?$paymentId)`            | The provider would not create the payment.                | With an id: `attempt_started` plus `status_reported: failed`. Without one: no entry (there is no attempt to file it under), and the column reads `failed` on its own. No redirect either way. |

A refused order ends up `failed`, dispatches `PaymentFailed` and stays
[re-placeable](orders.md#re-placement), with the basket still standing. One
exception: if an earlier attempt already captured money, the money decides the
status, exactly as it does everywhere else.

The redirect is the package's rather than the gateway's so the attempt is
recorded **before** the visitor leaves. The shipped redirector exits, and
nothing after it runs.

## The ledger

One table, append-only. Nothing updates or deletes an entry.

| Column       | Holds                                                                        |
| ------------ | ---------------------------------------------------------------------------- |
| `order`      | The order. Empty only on an `unknown_payment` entry.                         |
| `provider`   | `PaymentInterface::provider()`.                                              |
| `payment_id` | The provider's id for the attempt.                                           |
| `kind`       | One of the eight kinds below (`EntryKind`).                                  |
| `status`     | The reported status, on a `status_reported` entry.                           |
| `amount`     | Cents, zero or more, on a money entry. The kind says which way it went.      |
| `reference`  | The provider's own id for a money movement.                                  |

| Kind                  | Written when                                                                |
| --------------------- | --------------------------------------------------------------------------- |
| `attempt_started`     | `pay()` answered with a payment id. The first entry of every attempt.       |
| `status_reported`     | A report's status differs from that attempt's last reported status.         |
| `captured`            | Money arrived.                                                              |
| `refunded`            | Money went back.                                                            |
| `refund_reversed`     | A refund was undone.                                                        |
| `chargeback`          | The customer's bank took the money back.                                    |
| `chargeback_reversed` | A chargeback was undone.                                                    |
| `unknown_payment`     | A webhook named a payment id no attempt carries.                            |

**An attempt** is the set of entries sharing a `payment_id`. There is no
attempts table. `ecommerce_order.payment_id` stays as the pointer to the
**current** attempt. Only the package writes it: it is cleared at the start of
`Cart::place()` and set when an attempt starts. Money counts on **any**
attempt. "Current" only decides whose status reports describe the order.

**Idempotency is the table's.** A money movement is unique on
`(provider, kind, reference)`, the provider's own id for it. So a report
applied twice writes nothing the second time. A status entry is written only
when the status changed, so a replayed report writes no status either. A
pending report on a fresh attempt is no change, because an attempt starts out
pending.

**A reversal needs its counterpart.** A `refund_reversed` or
`chargeback_reversed` entry is written only if the `refunded` or `chargeback`
with the same provider and reference is already in the ledger, or arrives in
the same report.

**Unknown ids are recorded.** A webhook about a payment id no attempt carries
writes one `unknown_payment` entry, with no order, and then still throws
`UnknownPayment`.

The order answers its money from the entries. See
[Orders](orders.md#money-received):

```php
$order->getPaid();        // Σ captured
$order->getReturned();    // Σ refunded − Σ refund_reversed + Σ chargeback − Σ chargeback_reversed
$order->getNet();         // paid − returned
$order->getOutstanding(); // max(0, total − net)
```

## Deriving the status

`payment_status` is derived from the entries by `PaymentLedger` after every
write. There is no setter. The rule is checked top to bottom, and the first
row that matches decides:

| Entries say                                                                  | Status                                                     |
| ---------------------------------------------------------------------------- | ---------------------------------------------------------- |
| A capture, and net ≥ the order's total                                       | `paid`                                                     |
| The current attempt captured, or there is no current attempt: paid > 0 and net ≤ 0 | `refunded`                                           |
| ...the same, returned > 0                                                    | `partially_refunded`                                       |
| ...the same, anything else (including no capture at all)                    | `paid` (or `pending` when nothing was ever captured)       |
| The current attempt captured nothing of its own                              | That attempt's last reported status, else `pending`        |

The first row covers a free order: a €0 capture keeps net 0 ≥ total 0, so the
order is paid. The last row covers a re-placed order waiting on its new
attempt. The old attempts' money still counts toward the figures, but it does
not describe an order that is waiting to be paid again. A fully refunded order
that is re-placed reads `pending` (or whatever its new attempt reports). Once
it pays again it reads `paid`, not `partially_refunded`, because net covers
the total.

The ledger dispatches the matching event **only when the derived status
changes**:

| Status               | Event                      |
| -------------------- | -------------------------- |
| `paid`               | `Paid`                     |
| `failed`             | `PaymentFailed`            |
| `canceled`           | `PaymentCanceled`          |
| `expired`            | `PaymentExpired`           |
| `refunded`           | `PaymentRefunded`          |
| `partially_refunded` | `PaymentPartiallyRefunded` |
| `pending`            | nothing                    |

Several things follow from the rule, and none of them needs a transition
guard:

- **A late `expired` after a capture leaves the order `paid`.** The report is
  recorded and the money outranks it.
- **A replayed report changes nothing and dispatches nothing.**
- **A failed attempt is no dead end.** Without money, the latest report
  decides.
- **A superseded attempt still counts.** A customer who abandons the first
  checkout and pays it after a re-placement has paid, as long as the capture
  covers the total. The webhook resolves through the ledger, and the capture
  lands on the order.

Read it back through the enum:

```php
use Tnt\Ecommerce\Payment\PaymentStatus;

$order->getPaymentStatus() === PaymentStatus::Paid;
```

`getPaymentStatus()` reads anything it cannot parse as `Pending`. That includes
the empty string on orders from before the lifecycle existed. Pending is the
one status that claims nothing. This is the **payment's** state, not a
fulfillment status.

### The two kinds of refund

A refund is not one thing. A shop that gives €1 back on a €100 order has not
undone that order. One that gives the whole €100 back has. The ledger tells
them apart by the amounts, so no gateway has to:

- **`partially_refunded`**: some of the money went back, and the order is
  still an order somebody paid for. It is not re-placeable, exactly as `paid`
  is not.
- **`refunded`**: every cent captured went back. Nobody has paid for that order
  any more, so it **is** re-placeable.

## Paid releases the cart

Placing an order deliberately leaves the cart standing. With an asynchronous
gateway, a failed or canceled payment must bring the visitor back to a basket
that is still there. The basket is finally spent at `Paid`, so the provider's
listener follows the [cart→order link](cart.md#the-cart-order-link) and
**soft-deletes** the cart: `ecommerce_cart.deleted = time()`, found through
`CartRepository::byOrder()`. The row survives with its order link intact, for
provenance. Both storages treat it as absent from then on, so the payer's next
visit starts an empty cart. The same `Paid` also redeems the order's coupon
([Discounts](discounts.md)).

Those are the only listeners the package registers. `Paid` fires from the
ledger's dispatch, once per change to `paid`. A synchronous shop that still
calls `$cart->clear()` after checkout keeps working, because the hard delete
just gets there first.

## Writing a gateway

`PaymentInterface` is all a synchronous gateway needs. `NullPayment` is the
worked example. A real, asynchronous provider also implements the webhook
half:

```php
interface PaymentGatewayInterface extends PaymentInterface
{
    public function reportOf(string $paymentId): PaymentReport;
}
```

Set it the usual way, with `'payment' => \Tnt\Mollie\MolliePayment::class`,
and the provider binds it under both names.

### pay(): create the payment and say so

```php
public function provider(): string
{
    return 'mollie';
}

public function pay(OrderInterface $order): PaymentOutcome
{
    try {
        $payment = $this->provider->createPayment([
            'amount' => Money::toDecimal($order->getTotal()), // cents -> '12.50'
            // description, return URL, webhook URL: yours, from config
        ]);
    } catch (ProviderRejected $e) {
        return new PaymentRefused();
    }

    return new PaymentRedirect($payment->id, $payment->checkoutUrl);
}
```

- **Write nothing and redirect nowhere.** The package records the attempt,
  points `payment_id` at it and sends the visitor. A re-placement calls
  `pay()` again, and the new attempt becomes current while the old one's
  entries stay.
- **Amounts come from `Money::toDecimal()`.** The order's money is integer
  cents, and providers want `'12.50'` strings. Do not divide by 100.
- **A refusal is an outcome.** Swallowing it would leave the order pending
  forever, with nobody on the way to pay it.

### reportOf(): tell the whole story, every time

When the provider announces a change, the package's `PaymentWebhook` finds the
order through the ledger (`provider` + `payment_id`, on any attempt). It then
asks the gateway for a report and hands it to `PaymentLedger::apply()`.
Interrogate the provider's API, and never trust the webhook body:

```php
public function reportOf(string $paymentId): PaymentReport
{
    $payment = $this->provider->getPayment($paymentId);
    $movements = [];

    if ($payment->isPaid()) {
        $movements[] = new Movement(
            EntryKind::Captured,
            $payment->id,
            Money::fromDecimal($payment->amount)
        );
    }

    foreach ($payment->refunds() as $refund) {
        // Only once the refund can no longer be canceled.
        $movements[] = new Movement(
            EntryKind::Refunded,
            $refund->id,
            Money::fromDecimal($refund->amount)
        );
    }

    return new PaymentReport($paymentId, $this->statusFor($payment), $movements);
}
```

- **Report everything, every time.** Every movement the provider knows of,
  each under the provider's own id. The ledger writes only what is new, so a
  full report is always safe to repeat.
- **A paid report carries its capture.** Money is what makes an order paid.
  A `paid` status with no `captured` movement reads as a reported status, not
  as money.
- **Report a refund only once it is final.** A refund the provider can still
  cancel is not money returned yet. A `*_reversed` movement is written only if
  its counterpart is already in the ledger.
- **Map the provider's vocabulary onto `PaymentStatus`.** `Pending` is the
  honest answer for a payment still open.

### What the project wires

The package is route-agnostic, so a project on an asynchronous gateway
registers one webhook route and points it at the handler:

```php
// The provider POSTs its payment id; hand it to the package.
'payment-webhook/' => function ($request) use ($app) {
    $app->get(\Tnt\Ecommerce\Payment\PaymentWebhook::class)->handle(
        $request->post->string('id')
    );
},
```

`handle()` throws `UnknownPayment` when no attempt carries the id, after
recording one `unknown_payment` entry. Answer the provider with a 404 and let
it retry or give up. Swallowing the exception would tell a provider posting
garbage that all is well.

The **return page**, where the provider sends the visitor back, reads the
order's own state and nothing else. The webhook may or may not have arrived
first, so the page asks `getPaymentStatus()`. `Paid` gets a thank-you,
`Pending` gets "we are confirming your payment", and a failed, canceled or
expired attempt offers the still-standing cart again
([re-placement](orders.md#re-placement)). Never conclude anything from query
parameters. The visitor's return proves only that they came back.

## Available gateways

- **Mollie**: `dry-mollie`, https://github.com/reinvanoyen/dry-mollie

`dry-mollie` is pinned to `dry-ecommerce: ^1.2.1` and **cannot be installed
alongside the 4.x line**. Until it is ported to the ledger contract, a project
on this version has only `NullPayment` and cannot take a payment at all.

## See also

- [Orders](orders.md): what an order looks like when `pay()` receives it,
  and its money figures
- [Discounts](discounts.md): why redemption hangs off `Paid`
