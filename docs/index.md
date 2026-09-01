# dry-ecommerce

An e-commerce package for dry3: a cart, a checkout, orders, stock, tax and an
address book, with the parts a shop has to decide for itself left as interfaces
rather than guessed at.

## Start here

- [**Installation**](installation.md) — composer, the service provider, config,
  migrations, and making your first model sellable. Read this first.
- [**Money**](money.md) — everything is an `int` of cents, and there is one
  rounding rule the whole package obeys. Read this second; it explains figures
  that otherwise look wrong.

## Concepts

|                                       |                                                                                     |
| ------------------------------------- | ----------------------------------------------------------------------------------- |
| [Buyable](buyable.md)                 | The contract a sellable model implements, and the two capabilities it can opt into. |
| [Cart](cart.md)                       | What is in it, what it costs, and turning it into an order.                         |
| [Options and variants](options.md)    | Per-line choices: part of the merge key, frozen onto the order line.                |
| [Customer](customer.md)               | Guests, accounts, and the dry-accounts pairing.                                     |
| [Addresses](addresses.md)             | The address book, and the copy an order freezes at checkout.                        |
| [Orders](orders.md)                   | Drafts and placement, what an order records, and the reference a customer quotes.   |
| [Fulfillment](fulfillment.md)         | Delivery methods, their cost, and the attributes they collect.                      |
| [Discounts and coupons](discounts.md) | Codes, the rules behind them, and when they are spent.                              |
| [Stock](stock.md)                     | Counting what there is, and what happens when it runs out.                          |
| [Tax](tax.md)                         | Rates, and the one fact the package cannot infer: whether your prices include them. |
| [Payment](payment.md)                 | The one-method gateway interface, and why the default charges nobody.               |

## Migrating from 1.x

- [**What changed from 1.x**](from-1x.md) — the domain moved substantially. If
  you are looking at a project on the old line, read this before assuming
  anything here applies to it.

## The three things that surprise people

**1. Money is cents, everywhere.** `getPrice()` returns `1250` for €12.50. A
model whose column holds euros converts in `getPrice()` and nowhere else. See
[Money](money.md).

**2. Stock and tax are optional.** `BuyableInterface` asks for five things and
none of them is a stock worker or a tax rate. A buyable opts into
`HasStockInterface` and `TaxableInterface` separately, or into neither — and
neither is a complete, ordinary buyable. See [Buyable](buyable.md).

**3. The package reports; the shop decides.** `canAdd()` says whether the stock
covers a quantity and `add()` adds regardless. Refusing the sale, backordering
and overselling on purpose are all real ways to run a shop, and the package has
not been told which one applies. See [Cart](cart.md).

## Known gaps

Honest list, kept here rather than discovered one at a time. Most were found
by building a full shop (Delhaize Nederename) on the package; that project's
`docs/dry-ecommerce.md` carries the detailed findings.

(One tax rate per line is _by design_, not a gap — differently-taxed add-ons
become their own lines, shop-side. [Options](options.md).)

- **`FulfillmentInterface` has no availability concept.** A method cannot say
  when or whether it applies; every shop with dates/slots/capacity builds a
  side service the contract never mentions. [Fulfillment](fulfillment.md).
- **Frozen fulfillment attributes hold ids, not display values**, and there is
  no query surface over the frozen JSON.
- **`OrderItemInterface` has no `getId()`.** A shop that adds its own columns
  to `ecommerce_order_item` (a parent link, say) cannot reach a line's row
  through the contract — reading them back means a downcast to the concrete
  model. [Orders](orders.md#order-lines).
- **No write-side default helpers on the address book.** One-default-per-type
  is enforced only by `AmbiguousAddress` at read time.
- **Checkout needs a web request.** `AccountsUserResolver` reads the session
  unconditionally, so a checkout in a cron or a queue worker fails on a null
  session id. [Installation](installation.md#checking-it-works).
- **`getThumbnailSource()` is required and never read.** Nothing in the package
  consumes it. [Buyable](buyable.md).

## Syncing to Obsidian

```sh
make sync-docs      # needs OBSIDIAN_DOCS_PATH in .env
```
