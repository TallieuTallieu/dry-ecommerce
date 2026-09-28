# Changelog

All notable changes to this package are documented in this file. Versions
follow [Semantic Versioning](https://semver.org). New entries are generated from
commit messages by [dry-ci](https://github.com/TallieuTallieu/dry-ci); past
entries may be edited by hand.

## 3.13.2 - 2026-09-28

No notable changes.

## 3.13.1 - 2026-09-18

### Other changes

- Support oak 4 alongside oak 3 ([sc-11482](https://app.shortcut.com/tallieu--tallieu/story/11482))
- **deps:** Lock oak 4 and accept dry-accounts 4 ([sc-11482](https://app.shortcut.com/tallieu--tallieu/story/11482))

## 3.13.0 - 2026-09-18

### Features

- **admin:** A read-only Payments portal with a Ledger screen of every payment entry; a project opts in from its admin configuration ([sc-11459](https://app.shortcut.com/tallieu--tallieu/story/11459))

## 3.12.0 - 2026-09-18

### Breaking changes

- **payment:** `PaymentInterface::pay()` now returns a `PaymentOutcome` (`PaymentRedirect`, `PaymentSettled` or `PaymentRefused`) instead of redirecting or dispatching events itself, and gateways implement the new `provider()` ([sc-11458](https://app.shortcut.com/tallieu--tallieu/story/11458))
- **payment:** `PaymentGatewayInterface::statusOf()` is replaced by `reportOf()`, which returns a `PaymentReport` of what the provider reports now ([sc-11458](https://app.shortcut.com/tallieu--tallieu/story/11458))
- **payment:** `payment_status` is derived from the ledger; `setPaymentStatus()`, `PaymentStatus::canTransitionTo()` and the status-writing listeners are removed ([sc-11458](https://app.shortcut.com/tallieu--tallieu/story/11458))

### Features

- **payment:** Payment ledger as the source of truth for an order's money: every attempt, status report and money movement is an append-only row in `ecommerce_payment_entry`, and replayed webhooks write once ([sc-11458](https://app.shortcut.com/tallieu--tallieu/story/11458))

## 3.11.0 - 2026-09-18

### Breaking changes

- Custom implementations of the contracts gain members: `AddressInterface::getBox()`, `CartInterface`/`CartStorageInterface::childrenOf()`, `CartItemInterface::getParent()`/`setParent()`, `OrderItemInterface::getParent()`, and `OrderInterface::add()` now returns an `OrderItemInterface` ([sc-11448](https://app.shortcut.com/tallieu--tallieu/story/11448))
- A project that added its own `ecommerce_order_item.parent` or `box` column must drop its revision and the column before migrating (see docs/installation.md) ([sc-11448](https://app.shortcut.com/tallieu--tallieu/story/11448))

### Features

- **payment:** Partial refunds get their own `PartiallyRefunded` status and `PaymentPartiallyRefunded` event, and a fully refunded order can be placed again ([sc-11448](https://app.shortcut.com/tallieu--tallieu/story/11448))
- **cart:** Lines can hang off a parent line; removing a line removes its children, and placement copies the link to the order lines ([sc-11448](https://app.shortcut.com/tallieu--tallieu/story/11448))
- **address:** A `box` (bus number) field on the address book and both frozen order addresses ([sc-11448](https://app.shortcut.com/tallieu--tallieu/story/11448))

### Fixes

- **schema:** Index `ecommerce_order_item` on the buyable behind each line ([sc-11448](https://app.shortcut.com/tallieu--tallieu/story/11448))

## 3.10.0 - 2026-09-02

### Features

- **payment:** Payment provider harness: the opt-in `PaymentGatewayInterface`, a `RedirectorInterface` seam and the `PaymentWebhook` handler as the default gateway shape ([sc-11346](https://app.shortcut.com/tallieu--tallieu/story/11346))

### Other changes

- Cover the pay() failure path in the gateway guide ([sc-11346](https://app.shortcut.com/tallieu--tallieu/story/11346))

## 3.9.1 - 2026-09-01

### Fixes

- **account:** SyncsCustomer::save() declares : void ([sc-11258](https://app.shortcut.com/tallieu--tallieu/story/11258) follow-up)

## 3.9.0 - 2026-09-01

### Breaking changes

- **customer:** `ecommerce_customer.user` is now unique; merge duplicate customer rows for one account before migrating ([sc-11258](https://app.shortcut.com/tallieu--tallieu/story/11258))
- **fulfillment:** Custom `FulfillmentInterface` implementations add `attributeOr()` (free via `HasFulfillmentAttributes`) ([sc-11258](https://app.shortcut.com/tallieu--tallieu/story/11258))

### Features

- **customer:** One account, one customer row — enforced and served: `Customer::forUser()`, `OrderRepository::forUser()` and the opt-in `SyncsCustomer` trait ([sc-11258](https://app.shortcut.com/tallieu--tallieu/story/11258))

## 3.8.2 - 2026-08-31

### Fixes

- **cart:** Slide the cookie's expiry with every visit ([sc-11260](https://app.shortcut.com/tallieu--tallieu/story/11260))
- **order:** Spare a stale draft while its basket is in use ([sc-11260](https://app.shortcut.com/tallieu--tallieu/story/11260))
- **order:** Make every column placement writes nullable, so a draft row is valid under MySQL strict mode ([sc-11260](https://app.shortcut.com/tallieu--tallieu/story/11260))

## 3.8.1 - 2026-08-31

### Fixes

- **schema:** Index the columns the repositories filter on ([sc-11263](https://app.shortcut.com/tallieu--tallieu/story/11263))
- **schema:** Index the customer email the admin lookup filters on ([sc-11263](https://app.shortcut.com/tallieu--tallieu/story/11263))

## 3.8.0 - 2026-08-31

### Breaking changes

- **order:** `OrderInterface::getCustomer()` returns `?CustomerInterface`; a guest order has no customer row ([sc-11260](https://app.shortcut.com/tallieu--tallieu/story/11260))
- **cart:** Custom `CartStorageInterface` implementations add `getOrderId()`, `setOrderId()`, `getFulfillmentAttributes()` and `setFulfillmentAttributes()`; the fulfillment attributes move off the session ([sc-11260](https://app.shortcut.com/tallieu--tallieu/story/11260))

### Features

- **order:** Draft orders, cookie carts and the cart-order link: `Cart::place()`, `ecommerce.cart_lifetime` and the `ecommerce:reap-drafts` command ([sc-11260](https://app.shortcut.com/tallieu--tallieu/story/11260))

## 3.7.0 - 2026-08-26

### Breaking changes

- **address:** An address is a where: `AddressInterface::getFirstName()` and `getLastName()` are removed, and a revision drops the address name columns and the frozen order address name columns; read the name off the order instead ([sc-11257](https://app.shortcut.com/tallieu--tallieu/story/11257))

## 3.6.1 - 2026-08-26

### Fixes

- **options:** Compare the merge key byte for byte; requires dry-dbi ^3.13 ([sc-11261](https://app.shortcut.com/tallieu--tallieu/story/11261))
- **order:** Review fixes — status guard, line timestamps, one JSON convention ([sc-11261](https://app.shortcut.com/tallieu--tallieu/story/11261))

## 3.6.0 - 2026-08-26

### Breaking changes

- **cart:** Custom cart contracts gain the line-options members: `add()` takes `$options`, `CartInterface`/`CartStorageInterface` add `updateQuantity()` and `removeItem()`, `CartItemInterface` adds `getOptions()`, and `OrderItemInterface` adds `getPrice()` and `getOptions()`; `remove()` now removes every variant ([sc-11176](https://app.shortcut.com/tallieu--tallieu/story/11176))

### Features

- **order:** Payment status lifecycle, frozen fulfillment attributes, per-line options ([sc-11176](https://app.shortcut.com/tallieu--tallieu/story/11176))

### Other changes

- Split the README into a docs/ set, one page per concept ([sc-11175](https://app.shortcut.com/tallieu--tallieu/story/11175))
- **src:** Slim the inline docblocks; the prose lives in docs/ ([sc-11176](https://app.shortcut.com/tallieu--tallieu/story/11176))

## 3.5.0 - 2026-08-25

### Breaking changes

- **customer:** Custom `CustomerInterface` implementations add `getEmail()`, `getCompanyName()` and `getVatNumber()` ([sc-11172](https://app.shortcut.com/tallieu--tallieu/story/11172))
- **customer:** The twelve inline address columns leave `ecommerce_customer`; an existing shop moves them into `ecommerce_address` by hand with the SQL in the docs, backfilling the orders first ([sc-11172](https://app.shortcut.com/tallieu--tallieu/story/11172))

### Features

- **address:** Give a customer an address book, and freeze what an order used ([sc-11172](https://app.shortcut.com/tallieu--tallieu/story/11172))
- **customer:** One row per account, and the identity an order was placed under ([sc-11172](https://app.shortcut.com/tallieu--tallieu/story/11172))

### Fixes

- **money:** Refuse an apportionment that cannot be done ([sc-11172](https://app.shortcut.com/tallieu--tallieu/story/11172))

## 3.4.0 - 2026-08-25

### Breaking changes

- **tax:** `TaxRateInterface::getTax(int): int` is replaced by `getPercentage(): int|float` ([sc-11195](https://app.shortcut.com/tallieu--tallieu/story/11195))
- **tax:** Custom `OrderInterface` implementations add `getTax()`; the `tax` and `prices` columns are added to the create revision, so an existing installation adds them itself ([sc-11195](https://app.shortcut.com/tallieu--tallieu/story/11195))

### Features

- **money:** Find the rate inside an amount, and split one exactly ([sc-11195](https://app.shortcut.com/tallieu--tallieu/story/11195))
- **tax:** Charge tax, not just report it: `ecommerce.prices` (inclusive or exclusive) and `ecommerce.delivery_tax_rate` ([sc-11195](https://app.shortcut.com/tallieu--tallieu/story/11195))

## 3.3.2 - 2026-08-25

### Breaking changes

- **order:** New order references have no underscore and use an upper-case alphabet without I, L, O and U; stop validating or parsing the old shape ([sc-11204](https://app.shortcut.com/tallieu--tallieu/story/11204))

### Fixes

- **order:** Draw the order reference from a source worth trusting (`random_int()` instead of `rand()`) ([sc-11204](https://app.shortcut.com/tallieu--tallieu/story/11204))

## 3.3.1 - 2026-08-24

### Other changes

- **checkout:** Run checkout end to end without a database ([sc-11203](https://app.shortcut.com/tallieu--tallieu/story/11203))
- **events:** Cover the coupon redeemed when an order is paid ([sc-11203](https://app.shortcut.com/tallieu--tallieu/story/11203))
- **checkout:** Say what checkout assigns, and test what the names claim ([sc-11203](https://app.shortcut.com/tallieu--tallieu/story/11203))

## 3.3.0 - 2026-08-24

### Breaking changes

- **customer:** `Cart`'s constructor takes a fourth argument, a `UserResolverInterface`; the customer `user` column is added to the create revision, so an existing installation adds it itself before configuring `AccountsUserResolver` ([sc-11171](https://app.shortcut.com/tallieu--tallieu/story/11171))

### Features

- **customer:** Link a checkout to the account behind it ([sc-11171](https://app.shortcut.com/tallieu--tallieu/story/11171))

## 3.2.0 - 2026-08-24

### Breaking changes

- **buyable:** Move `getStockWorker()` and `getTaxRate()` from buyables onto `HasStockInterface` and `TaxableInterface`, or delete them; `NullStockWorker` and `NullTaxRate` are removed ([sc-11173](https://app.shortcut.com/tallieu--tallieu/story/11173))
- **stock:** Custom `StockWorkerInterface` implementations take `int` instead of `float`, `decrement()` may now refuse with `StockWouldGoNegative`, and the container no longer binds `StockWorkerInterface` ([sc-11173](https://app.shortcut.com/tallieu--tallieu/story/11173))
- **cart:** Custom `CartInterface` and `CartStorageInterface` implementations add `canAdd()`/`getTax()` and `quantityOf()` ([sc-11173](https://app.shortcut.com/tallieu--tallieu/story/11173))

### Features

- **buyable:** Make stock and tax capabilities a buyable opts into ([sc-11173](https://app.shortcut.com/tallieu--tallieu/story/11173))

## 3.1.0 - 2026-08-23

### Breaking changes

- Represent money as integer cents: every money-carrying contract returns `int` cents instead of `float`, and the order money columns are `bigint` ([sc-11170](https://app.shortcut.com/tallieu--tallieu/story/11170))

### Features

- **money:** Refuse amounts and rates the arithmetic cannot hold exactly ([sc-11170](https://app.shortcut.com/tallieu--tallieu/story/11170))
- **money:** Add lineTotal and toDecimal ([sc-11170](https://app.shortcut.com/tallieu--tallieu/story/11170))
- **money:** Read an amount back out of text ([sc-11170](https://app.shortcut.com/tallieu--tallieu/story/11170))

### Other changes

- **revisions:** Pin the order money columns to bigint ([sc-11170](https://app.shortcut.com/tallieu--tallieu/story/11170))

## 3.0.0 - 2026-08-21

### Breaking changes

- **cart:** `Cart`'s constructor takes a `ShopInterface`, a `CartStorageInterface` and a `PaymentInterface` instead of the container and the shop ([sc-11169](https://app.shortcut.com/tallieu--tallieu/story/11169))

### Fixes

- **cart:** Write the cart row only when something is added, instead of on every container resolution ([sc-11169](https://app.shortcut.com/tallieu--tallieu/story/11169))
- **discount:** Pass the cart or order to `isRedeemable()`, whose missing argument fatalled for any real coupon ([sc-11169](https://app.shortcut.com/tallieu--tallieu/story/11169))

### Other changes

- Put a storage seam under the cart (`CartStorageInterface`, with session and in-memory storage) and add repositories ([sc-11169](https://app.shortcut.com/tallieu--tallieu/story/11169))

## 1.2.3 - 2026-08-21

### Other changes

- Same code as 3.0.0.

## 1.2.2 - 2026-08-20

### Breaking changes

- Requires PHP 8.4 or later ([sc-11168](https://app.shortcut.com/tallieu--tallieu/story/11168))
- Requires oak ^3.0 (was ^1.0 | ^1.1), tallieutallieu/dry ^4.0 and tallieutallieu/dry-dbi ^3.0 ([sc-11167](https://app.shortcut.com/tallieu--tallieu/story/11167))

### Fixes

- Call Str::random instead of the removed string\random function ([sc-11178](https://app.shortcut.com/tallieu--tallieu/story/11178))

### Other changes

- Add AGENTS.md with main branch and tracker mapping ([sc-11167](https://app.shortcut.com/tallieu--tallieu/story/11167))
- Record that Shortcut state is driven by the branch ([sc-11178](https://app.shortcut.com/tallieu--tallieu/story/11178))
- Add docker dev environment, static analysis, tests and CI ([sc-11168](https://app.shortcut.com/tallieu--tallieu/story/11168))

## Earlier history

- **1.0.0** (2019-10-15): First tagged release by Rein Van Oyen as `reinvanoyen/dry-ecommerce`: cart, checkout, orders, discounts, fulfillment, payment and stock. 1.0.1 is the same commit.
- **1.0.2** (2019-10-16), **1.0.3** and **1.0.4** (2019-11-26): Dependency fixes and further work on the first version.
- 2021-03-23: The repository moved to Tallieu & Tallieu and the package was renamed `tallieutallieu/dry-ecommerce` (on `tallieutallieu/oak`); the `Tnt\Ecommerce` namespace stayed.
- **1.0.5** (2023-04-13): Add a callback to the cart checkout.
- **1.1** (2023-11-16): Merge with `reinvanoyen/dry-ecommerce`, pulling in its changes, and add the checkout callback.
- **1.1.1** (2024-10-22): Add `__toString()` to the Customer model.
- **1.2.0** (2024-10-22): Revert the accidental copy of the whole package into the Cart folder, and add `__toString()` to the Customer model again.
- **1.2.1** (2025-06-18): Allow oak 1.1.
- There is no 2.x line: the modernised package went from 1.2.3 to 3.0.0.

See the git tags before 1.2.2 for the full history.
