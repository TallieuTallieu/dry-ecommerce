# Address

A customer keeps an **address book**: `ecommerce_address`, one row per address,
as many as they have, each with an `AddressType` saying what it is for.

```php
use Tnt\Ecommerce\Address\AddressType;
use Tnt\Ecommerce\Model\Address;

$address = new Address();
$address->customer = $customer;
$address->setType(AddressType::Shipping);
$address->first_name = 'Ada';
$address->last_name = 'Lovelace';
$address->street = 'Kortrijksesteenweg';
$address->number = '1144';
$address->postal_code = '9051';
$address->city = 'Gent';
$address->country = 'BE';
$address->save();
```

`AddressType` has two cases, `Billing` and `Shipping`, because those are the two
questions a shop asks of an address: where the invoice goes and where the parcel
goes. A "home" or "work" label is a shop's own vocabulary for its customers'
addresses and means nothing to a checkout, so it is not modelled here.

The recipient's name lives on the address, not on the customer. A parcel can go
to somebody else — a gift, a colleague, a neighbour who is in during the day.

## Reading the book

```php
$customer->getAddresses();                        // iterable<Address>
$customer->getAddress(AddressType::Shipping);     // ?AddressInterface
```

`getAddress()` answers with the **most recently added** address of that kind.
That is a default for the shop that never asks, not a rule. A shop that lets a
customer pick at checkout says which one it picked:

```php
$customer->useAddress($chosen);   // for this request; nothing is written
$cart->checkout($customer);
```

A customer with no address of that kind answers `null`, and nothing is
substituted from the other kind — see below.

## The order takes a copy

**An order never points at the address book.** At checkout,
`Order::freezeCustomer()` copies the identity and both addresses onto the
order's own columns:

```
first_name  last_name  email
billing_first_name  billing_last_name  billing_street  billing_number
billing_postal_code  billing_city  billing_country
shipping_… (the same seven)
```

The reason is that an address book is *edited*. A customer moves house and
corrects the address on file; a customer deletes an address they used once. Both
are things a book must let them do, and an order that read through to those rows
would answer, next year, that last year's parcel went somewhere it never went —
or that it went nowhere, the row having been deleted. **An invoice is a
statement about the past, and a mutable row cannot back one.**

`Order.customer` is still a foreign key, and it still answers "whose account is
this order on". Only the frozen copy answers "who placed it and where did it
go", and only the frozen copy is safe to print.

```php
$order->getFirstName();        // as it was at checkout
$order->getEmail();
$order->getShippingAddress();  // Tnt\Ecommerce\Address\FrozenAddress
$order->getBillingAddress();
```

`FrozenAddress` implements the same `AddressInterface` as `Address`, so one
template renders either, but it is readonly and has nothing behind it to
re-read. `isEmpty()` asks whether the order recorded an address of that kind at
all, and `isDefault()` always answers false — the mark says which address to
reach for next time, and a frozen copy is not reaching for anything.

`AddressRepository` exists for queries that cross customers — admin screens,
exports. Inside one customer's book, `Customer::getAddresses()` /
`getAddress()` are the intended readers.

## Nothing is substituted for a missing address

A customer with a shipping address and no billing address freezes seven blank
billing columns, and vice versa. An order carrying an address the customer never
gave for that purpose is a worse record than one that admits the purpose had no
address — and it is a record nobody can correct afterwards, because it looks
exactly like an address that *was* given.

A shop that means "bill me where you ship" says so by keeping an address of each
kind, which is precisely the thing the book can now express and the twelve
inline columns could not.

## Customers that keep no addresses

`HasAddressesInterface` is a capability, in the same shape as
`HasStockInterface` and `TaxableInterface`: `Cart::checkout()` asks with
`instanceof`, and a customer that does not implement it is not asked. A shop
selling downloads, or one with its own customer class, freezes both sides blank
and checks out exactly as before.

## Company and VAT number

An account can be opened in the name of a business. When it is, the company name
and the VAT number are what the account *is* — one identity, not two facts that
happen to travel together — so both sit on the customer:

```php
$customer->company = 'Acme NV';
$customer->vat = 'BE0123456789';
```

`CustomerInterface` requires `getCompanyName()` and `getVatNumber()`, both
returning `''` for an account opened by a person. Every customer can answer both
honestly, which is why neither is behind a capability interface.

Both are **frozen onto the order** at checkout, beside the name and the email.
A business that is sold or restructured, or a VAT number corrected afterwards,
does not reopen the invoices the old details were on.

> [!note] A postal company line is a different thing, and is not here yet
> An address block can carry a company of its own: the invoice goes to a head
> office and the parcel goes to a branch, and a delivery label without that name
> on it does not get past a post room. The field above cannot stand in for it —
> one account has one company name, and a customer may post to several places.
>
> Deferred rather than solved. A shop needing a different name on the label has
> nowhere to put it today. When one does, it belongs on `AddressInterface`, and
> the account's company name stays where it is.

## Choosing an address

A book may hold several addresses of a kind — home and work, one warehouse and
another. Mark one of each kind as the **default**, and that is the one a
checkout takes:

```php
$address->is_default = 1;
$address->save();
```

A book holding one address of a kind needs no mark: with nothing to choose
between, that one is the answer.

If a book holds several of a kind and marks none — or marks two —
`Customer::getAddress()` raises `Tnt\Ecommerce\AmbiguousAddress` rather than
picking. That is deliberate. A checkout that stops costs somebody a moment; a
checkout that guesses sends the parcel to the wrong address and produces an
order that reads perfectly, with nothing anywhere to say it went wrong.

To settle it for one checkout without touching the book — a customer choosing a
delivery address at the till — name the address:

```php
$customer->useAddress($address);   // this request only, nothing written
$order = $cart->checkout($customer);
```


