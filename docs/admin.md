# Admin

The package ships one admin screen: a read-only **Ledger** of every payment
ledger entry. It has no Orders screen. A shop builds its own, because what an
order screen shows is the shop's business.

## Turning it on

Nothing registers automatically. A project opts in with one line in its
`app/configuration/admin.inc.php`:

```php
admin\Router::$modules[] = Application::get()->get(
    Tnt\Ecommerce\Contracts\LedgerPortalInterface::class
);
```

That adds a **Payments** portal holding the **Ledger** manager.

Why the admin config and not the provider's `boot()`: `boot()` runs on every
front-end request, and `admin\Router::route()` replaces the module list for a
visitor who is not signed in. The binding is lazy, so a request that never
asks for the portal never builds it.

## The Ledger

A flat list, newest first, 25 to a page. The columns:

| Column     | Shows                                                                   |
| ---------- | ----------------------------------------------------------------------- |
| Created    | Date and time the entry was written.                                    |
| Order      | The order's row id, and its public reference (`order_id`) once placed. |
| Provider   | `PaymentInterface::provider()`.                                         |
| Payment id | The provider's id for the attempt, with a copy button.                  |
| Kind       | The `EntryKind`.                                                        |
| Status     | The reported status, on a `status_reported` entry.                      |
| Amount     | `Money::toDecimal()` of the cents. Empty on an entry that moves none.   |
| Reference  | The provider's own id for a money movement.                             |

The search box matches the payment id, the reference, the order's row id and
its public reference.

**It cannot write.** The ledger is append-only and `PaymentLedger` is its one
writer (see [Payment](payment.md#the-ledger)). So the manager has no create,
delete or multi-delete action. A row opens in an `action\Edit` with
`'readonly' => true`: a view-only popup, whose save dry's API refuses.
