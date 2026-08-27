<?php

declare(strict_types=1);

namespace Tnt\Ecommerce\Cart;

use Tnt\Ecommerce\Contracts\BuyableInterface;
use Tnt\Ecommerce\Contracts\CartItemInterface;
use Tnt\Ecommerce\Contracts\CartStorageInterface;
use Tnt\Ecommerce\Model\Cart as CartModel;
use Tnt\Ecommerce\Model\CartItem;
use Tnt\Ecommerce\Model\DiscountCode;
use Tnt\Ecommerce\Repository\CartItemRepository;

/**
 * The row-backed half of a cart storage: everything about the `ecommerce_cart`
 * row and its lines, minus how the visitor's row is found and remembered —
 * that seam is what {@see SessionCartStorage} and {@see CookieCartStorage}
 * each answer their own way. A row is written only when something is actually
 * put in the cart. See docs/cart.md.
 */
abstract class DatabaseCartStorage implements CartStorageInterface
{
    protected ?CartModel $cart = null;

    /**
     * The visitor's cart row, by whatever this storage keys on — or null.
     * Soft-deleted rows are {@see existingCart()}'s to refuse; a lookup may
     * scope them out itself as well.
     *
     * @return CartModel|null
     */
    abstract protected function findCart(): ?CartModel;

    /**
     * Remember which row is the visitor's — a session key, a cookie.
     *
     * @param CartModel $cart
     * @return void
     */
    abstract protected function remember(CartModel $cart): void;

    /**
     * Stop pointing at any row.
     *
     * @return void
     */
    abstract protected function forget(): void;

    /**
     * The visitor's cart row if there already is one, without creating it. A
     * pointer at a vanished cart — or a soft-deleted one — reads as no cart
     * at all: a soft-deleted cart is absent everywhere.
     *
     * @return CartModel|null
     */
    protected function existingCart(): ?CartModel
    {
        if ($this->cart !== null) {
            return $this->cart;
        }

        $cart = $this->findCart();

        if ($cart !== null && $cart->deleted !== null) {
            $cart = null;
        }

        return $this->cart = $cart;
    }

    /**
     * The visitor's cart row, written and remembered if there is not one
     * already. Only the calls that put something in the cart use this. The
     * token is minted here, at row creation, whichever storage creates it —
     * it is the cart's portable name, never the row id.
     *
     * @return CartModel
     */
    protected function cart(): CartModel
    {
        $cart = $this->existingCart();

        if ($cart !== null) {
            return $cart;
        }

        $cart = $this->newCartRow();
        $cart->created = time();
        $cart->updated = time();
        $cart->token = bin2hex(random_bytes(16));
        $cart->save();

        $this->remember($cart);

        return $this->cart = $cart;
    }

    /**
     * The empty row {@see cart()} is about to fill in — a test seam, same
     * shape as {@see Cart::newOrder()}.
     *
     * @return CartModel
     */
    protected function newCartRow(): CartModel
    {
        return new CartModel();
    }

    /**
     * Marks a cart as touched. Callers that change a line have to say so: the
     * line lives in its own table, and saving it does not reach the cart.
     *
     * @param CartModel $cart
     * @return void
     */
    protected function touch(CartModel $cart): void
    {
        $cart->updated = time();
        $cart->save();
    }

    /**
     * @return array<int, CartItemInterface>
     */
    public function items(): array
    {
        $cart = $this->existingCart();

        if ($cart === null) {
            return [];
        }

        $items = [];

        foreach (CartItemRepository::create()->forCart($cart)->all() as $item) {
            $items[] = $item;
        }

        return $items;
    }

    /**
     * @param BuyableInterface $buyable
     * @param int $quantity
     * @param array<array-key, mixed> $options
     * @return void
     */
    public function add(
        BuyableInterface $buyable,
        int $quantity = 1,
        array $options = []
    ): void {
        $cart = $this->cart();

        $item = CartItemRepository::create()
            ->forBuyable($cart, $buyable, $options)
            ->firstOrNull();

        if ($item !== null) {
            $item->setQuantity($item->getQuantity() + $quantity);
            $this->touch($cart);

            return;
        }

        $item = new CartItem();
        $item->created = time();
        $item->updated = time();
        $item->cart = $cart;
        $item->item_id = (int) $buyable->getId();
        $item->item_class = get_class($buyable);
        $item->quantity = $quantity;

        // The canonical form, or NULL for no options — the same value the
        // lookup above compares on.
        $item->options = LineOptions::canonical($options);
        $item->save();

        $this->touch($cart);
    }

    /**
     * The sum across every option-variant of the buyable — stock counts the
     * buyable, not the selection.
     *
     * @param BuyableInterface $buyable
     * @return int
     */
    public function quantityOf(BuyableInterface $buyable): int
    {
        $cart = $this->existingCart();

        if ($cart === null) {
            return 0;
        }

        $quantity = 0;

        $items = CartItemRepository::create()
            ->forAnyVariantOf($cart, $buyable)
            ->all();

        foreach ($items as $item) {
            $quantity += $item->getQuantity();
        }

        return $quantity;
    }

    /**
     * Removes every variant of the buyable — a caller holding only a buyable
     * cannot name one.
     *
     * @param BuyableInterface $buyable
     * @return void
     */
    public function remove(BuyableInterface $buyable): void
    {
        $cart = $this->existingCart();

        if ($cart === null) {
            return;
        }

        $removed = false;

        $items = CartItemRepository::create()
            ->forAnyVariantOf($cart, $buyable)
            ->all();

        foreach ($items as $item) {
            $item->delete();
            $removed = true;
        }

        if ($removed) {
            $this->touch($cart);
        }
    }

    /**
     * @param string $itemId
     * @param int $quantity
     * @return void
     */
    public function updateQuantity(string $itemId, int $quantity): void
    {
        $cart = $this->existingCart();

        if ($cart === null) {
            return;
        }

        $item = $this->lineOf($cart, $itemId);

        if ($item === null) {
            return;
        }

        if ($quantity <= 0) {
            $item->delete();
        } else {
            $item->setQuantity($quantity);
        }

        $this->touch($cart);
    }

    /**
     * @param string $itemId
     * @return void
     */
    public function removeItem(string $itemId): void
    {
        $cart = $this->existingCart();

        if ($cart === null) {
            return;
        }

        $item = $this->lineOf($cart, $itemId);

        if ($item === null) {
            return;
        }

        $item->delete();
        $this->touch($cart);
    }

    /**
     * The line an id names, if it is a line of *this* visitor's cart — scoped
     * on purpose, or a basket-form POST could reach into another visitor's
     * cart by guessing row ids.
     *
     * @param CartModel $cart
     * @param string $itemId
     * @return CartItem|null
     */
    private function lineOf(CartModel $cart, string $itemId): ?CartItem
    {
        if (!ctype_digit($itemId)) {
            return null;
        }

        return CartItemRepository::create()
            ->forCart($cart)
            ->byId((int) $itemId)
            ->firstOrNull();
    }

    /**
     * Discard the cart — the row and its lines, hard. The soft path is not
     * this call: the Paid listener soft-deletes so the order link survives as
     * provenance. See docs/cart.md.
     *
     * @return void
     */
    public function clear(): void
    {
        $this->existingCart()?->delete();
        $this->cart = null;

        $this->forget();
    }

    /**
     * @return string|int|null
     */
    public function getFulfillmentId(): string|int|null
    {
        return $this->existingCart()?->fulfillment_method;
    }

    /**
     * @param string|int|null $id
     * @return void
     */
    public function setFulfillmentId(string|int|null $id): void
    {
        $cart = $this->cart();
        $cart->fulfillment_method = $id;

        $this->touch($cart);
    }

    /**
     * @return DiscountCode|null
     */
    public function getDiscount(): ?DiscountCode
    {
        return $this->existingCart()?->discount;
    }

    /**
     * @param DiscountCode|null $discount
     * @return void
     */
    public function setDiscount(?DiscountCode $discount): void
    {
        $cart = $this->cart();
        $cart->discount = $discount;

        $this->touch($cart);
    }

    /**
     * @return int|null
     */
    public function getOrderId(): ?int
    {
        $order = $this->existingCart()?->order;

        return $order === null ? null : (int) $order;
    }

    /**
     * @param int|null $id
     * @return void
     */
    public function setOrderId(?int $id): void
    {
        $cart = $this->cart();
        $cart->order = $id;

        $this->touch($cart);
    }

    /**
     * @return array<string, mixed>
     */
    public function getFulfillmentAttributes(): array
    {
        $decoded = LineOptions::decode(
            $this->existingCart()?->fulfillment_attributes
        );

        $attributes = [];

        foreach ($decoded as $name => $value) {
            $attributes[(string) $name] = $value;
        }

        return $attributes;
    }

    /**
     * @param array<string, mixed> $attributes
     * @return void
     */
    public function setFulfillmentAttributes(array $attributes): void
    {
        $cart = $this->cart();

        // The same canonical encoding the line options use — one JSON
        // convention per package. Empty stores NULL, like everywhere else.
        $cart->fulfillment_attributes = LineOptions::canonical($attributes);

        $this->touch($cart);
    }
}
