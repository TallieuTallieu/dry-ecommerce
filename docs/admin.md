# Admin

The package ships two admin screens: a read-only **Ledger** of every payment
ledger entry, and **Coupons** for [the shipped coupon](discounts.md#the-shipped-coupon).
It has no Orders screen. A shop builds its own, because what an order screen
shows is the shop's business.

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

| Column     | Shows                                                                  |
| ---------- | ---------------------------------------------------------------------- |
| Created    | Date and time the entry was written.                                   |
| Order      | The order's row id, and its public reference (`order_id`) once placed. |
| Provider   | `PaymentInterface::provider()`.                                        |
| Payment id | The provider's id for the attempt, with a copy button.                 |
| Kind       | The `EntryKind`.                                                       |
| Status     | The reported status, on a `status_reported` entry.                     |
| Amount     | `Money::toDecimal()` of the cents. Empty on an entry that moves none.  |
| Reference  | The provider's own id for a money movement.                            |
| Method     | How a capture was paid, and the card's brand when there is one.        |

The search box matches the payment id, the reference, the order's row id and
its public reference.

**It cannot write.** The ledger is append-only and `PaymentLedger` is its one
writer (see [Payment](payment.md#the-ledger)). So the manager has no create,
delete or multi-delete action. A row opens in an `action\Edit` with
`'readonly' => true`: a view-only popup, whose save dry's API refuses.

## Coupons

`Tnt\Ecommerce\Admin\CouponManager`. Not bound and not in any portal: a
project adds it to a portal of its own, in `app/configuration/admin.inc.php`:

```php
admin\Router::$modules[] = new dry\admin\Portal('shop', 'Shop', [
    // ... the shop's own managers
    new Tnt\Ecommerce\Admin\CouponManager(),
]);
```

The list, newest first, 25 to a page:

| Column  | Shows                                                   |
| ------- | ------------------------------------------------------- |
| Code    | The code a customer types.                              |
| Type    | Percentage or fixed amount.                             |
| Value   | `7.5%`, or the fixed amount in units.                   |
| Balance | What is left on a fixed coupon; empty for a percentage. |
| Uses    | `uses / max_uses`, or the count alone when unlimited.   |
| Valid   | `always`, `from …`, `until …`, or both dates.           |
| Note    | The shop's own note.                                    |

The search box matches the code (through `ecommerce_discount_code`) and the
note.

**Create** asks for the type, on two tabs that start on Percentage — an
`EnumSwitcher`, since dry's create form loads no row for a `Conditional` to
test. A percentage takes the percentage (`7,5` or `7.5`) and the maximum uses,
`1` by default; a fixed amount takes the amount in euros and no use limit, its
balance being the limit. Then the code — empty generates one; the first and
last day; the minimum in euros, held against the products that take a
discount; and a note.

**Edit** shows the type, a fixed coupon's amount and balance, and the uses
read-only; the code too, once the coupon has been used. The percentage, dates,
minimum, a percentage coupon's maximum uses and note stay editable. Saving
never writes the balance or the uses — only a redemption does.

**Redemptions**, a page of the coupon's own: every order it was spent on, with
the amount the order took off and what the coupon could not cover
(`claimed` while a redemption is unsettled). Read-only.

There is **no delete**. A coupon an order was placed with stays, so its
redemptions and the orders' codes keep pointing at something; end a coupon by
giving it a last day.
