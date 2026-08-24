<?php

namespace Tnt\Ecommerce\Cart;

use Oak\Session\Session;
use Tnt\Ecommerce\Contracts\BuyableInterface;
use Tnt\Ecommerce\Contracts\CartItemInterface;
use Tnt\Ecommerce\Contracts\CartStorageInterface;
use Tnt\Ecommerce\Model\Cart as CartModel;
use Tnt\Ecommerce\Model\CartItem;
use Tnt\Ecommerce\Model\DiscountCode;
use Tnt\Ecommerce\Repository\CartItemRepository;
use Tnt\Ecommerce\Repository\CartRepository;

/**
 * The shipped cart storage: the session says which cart row is the visitor's,
 * and the row plus its lines hold the rest.
 *
 * A row is written only when something is actually put in the cart. That is a
 * bigger change than it looks: the previous design created one from the cart's
 * constructor, so merely resolving the cart out of the container — on any page,
 * for any visitor, crawlers included — wrote a row and started a session.
 * Reading an empty cart now costs one lookup and no writes.
 *
 * The session arrives by injection rather than through its facade, which is
 * what makes {@see Cart} testable at all.
 */
class SessionCartStorage implements CartStorageInterface
{
    /**
     * The session key holding the current cart's id.
     */
    public const SESSION_KEY = 'cart';

    private Session $session;

    /**
     * The cart resolved for this request, if one has been resolved yet.
     */
    private ?CartModel $cart = null;

    /**
     * Repositories are built per query rather than injected: a dry-dbi
     * repository accumulates criteria, so one instance cannot serve two
     * different questions.
     *
     * @param Session $session
     */
    public function __construct(Session $session)
    {
        $this->session = $session;
    }

    /**
     * The visitor's cart row if there already is one, without creating it.
     *
     * A session pointing at a cart that no longer exists — cleared, pruned,
     * restored from a backup — is treated as no cart at all rather than as an
     * error.
     *
     * @return CartModel|null
     */
    private function existingCart(): ?CartModel
    {
        if ($this->cart !== null) {
            return $this->cart;
        }

        $id = $this->session->get(self::SESSION_KEY);

        if (!is_int($id) && !(is_string($id) && ctype_digit($id))) {
            return null;
        }

        return $this->cart = CartRepository::create()
            ->byId((int) $id)
            ->firstOrNull();
    }

    /**
     * The visitor's cart row, written and remembered in the session if there is
     * not one already. Only the calls that put something in the cart use this.
     *
     * @return CartModel
     */
    private function cart(): CartModel
    {
        $cart = $this->existingCart();

        if ($cart !== null) {
            return $cart;
        }

        $cart = new CartModel();
        $cart->created = time();
        $cart->updated = time();
        $cart->save();

        $this->session->set(self::SESSION_KEY, $cart->id);
        $this->session->save();

        return $this->cart = $cart;
    }

    /**
     * Marks a cart as touched. Callers that change a line have to say so: the
     * line lives in its own table, and saving it does not reach the cart.
     *
     * @param CartModel $cart
     * @return void
     */
    private function touch(CartModel $cart): void
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
     * @return void
     */
    public function add(BuyableInterface $buyable, int $quantity = 1): void
    {
        $cart = $this->cart();

        $item = CartItemRepository::create()
            ->forBuyable($cart, $buyable)
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
        $item->save();

        $this->touch($cart);
    }

    /**
     * One indexed lookup on `(cart, item_class, item_id)` — the same query
     * {@see add()} uses to find the line to merge into, which is what keeps the
     * two agreeing on what "the same buyable" means.
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

        return CartItemRepository::create()
            ->forBuyable($cart, $buyable)
            ->firstOrNull()
            ?->getQuantity() ?? 0;
    }

    /**
     * @param BuyableInterface $buyable
     * @return void
     */
    public function remove(BuyableInterface $buyable): void
    {
        $cart = $this->existingCart();

        if ($cart === null) {
            return;
        }

        $item = CartItemRepository::create()
            ->forBuyable($cart, $buyable)
            ->firstOrNull();

        if ($item === null) {
            return;
        }

        $item->delete();
        $this->touch($cart);
    }

    /**
     * @return void
     */
    public function clear(): void
    {
        $this->existingCart()?->delete();
        $this->cart = null;

        $this->session->set(self::SESSION_KEY, null);
        $this->session->save();
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
}
