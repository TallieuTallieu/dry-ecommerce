<?php

namespace Tnt\Ecommerce\Model;

use dry\orm\relationship\HasMany;

/**
 * A cart in progress, as stored in `ecommerce_cart`.
 *
 * Columns are magic properties on dry's ORM; the annotations below are what
 * lets static analysis see them. `deleted` is the soft-delete mark the Paid
 * listener writes — a soft-deleted cart keeps its `order` link forever, which
 * is the provenance the row survives for. See docs/cart.md.
 *
 * @property int|null $id
 * @property int $created
 * @property int $updated
 * @property int|null $deleted
 * @property string|null $token
 * @property int|null $order
 * @property string|int|null $fulfillment_method
 * @property string|null $fulfillment_attributes
 * @property DiscountCode|null $discount
 * @property-read HasMany $items
 */
class Cart extends \dry\orm\Model
{
    const TABLE = 'ecommerce_cart';

    /**
     * @var array<string, string>
     */
    public static $special_fields = [
        'discount' => DiscountCode::class,
    ];

    /**
     * @return HasMany
     */
    public function get_items()
    {
        return $this->has_many(CartItem::class, 'cart');
    }

    /**
     * @return void
     */
    public function delete()
    {
        /** @var CartItem $item */
        foreach ($this->items as $item) {
            $item->delete();
        }

        parent::delete();
    }
}
