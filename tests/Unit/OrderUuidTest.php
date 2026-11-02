<?php

declare(strict_types=1);

use Tests\Support\UuidV4;
use Tnt\Ecommerce\Order\OrderUuid;

it('writes a lower-case version 4 uuid', function (): void {
    for ($i = 0; $i < 200; $i++) {
        expect(OrderUuid::generate())->toMatch(UuidV4::PATTERN);
    }
});

it('never repeats itself', function (): void {
    $uuids = [];

    for ($i = 0; $i < 1000; $i++) {
        $uuids[] = OrderUuid::generate();
    }

    expect(array_unique($uuids))->toHaveCount(1000);
});
