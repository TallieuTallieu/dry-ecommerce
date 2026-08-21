<?php

namespace Tnt\Ecommerce\Model;

use dry\orm\Model;

/**
 * A place stock is counted, as stored in `ecommerce_stock`.
 *
 * A shop can have more than one — a warehouse, a shop floor — and addresses
 * them by `hid` rather than by primary key.
 *
 * @property int|null $id
 * @property int $created
 * @property int $updated
 * @property string $title
 * @property string $hid
 */
class Stock extends Model
{
    const TABLE = 'ecommerce_stock';
}
