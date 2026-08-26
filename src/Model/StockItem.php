<?php

namespace Tnt\Ecommerce\Model;

use dry\orm\Model;

/**
 * How many of one buyable a stock holds, as stored in `ecommerce_stock_item`.
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
