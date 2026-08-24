<?php

namespace Tnt\Ecommerce\Model;

use dry\orm\Model;

/**
 * How many of one buyable a stock holds, as stored in `ecommerce_stock_item`.
 *
 * The quantity is an `int`, and the docblock saying `float` was simply wrong:
 * the column has been an `int(11)` since {@see
 * \Tnt\Ecommerce\Revisions\CreateStockItemTable} created it, so a fractional
 * count was never storable however many signatures accepted one.
 *
 * @property int|null $id
 * @property int $created
 * @property int $updated
 * @property Stock|null $stock
 * @property int $item_id
 * @property string $item_class
 * @property int $quantity
 */
class StockItem extends Model
{
    const TABLE = 'ecommerce_stock_item';

    /**
     * @var array<string, string>
     */
    public static $special_fields = [
        'stock' => Stock::class,
    ];
}
