<?php

declare(strict_types=1);

/*
 * The other half of the session seam: fulfillment attributes.
 *
 * HasFulfillmentAttributes used to call the Session facade from four different
 * places, which meant no fulfillment method could be exercised at all. It now
 * writes through an AttributeStorageInterface that the shop hands it.
 */

use Tests\Support\FakeFulfillment;
use Tnt\Ecommerce\Fulfillment\InMemoryAttributeStorage;
use Tnt\Ecommerce\Fulfillment\MissingAttribute;
use Tnt\Ecommerce\Shop\Shop;

it('stores and reads back an attribute', function (): void {
    $fulfillment = new FakeFulfillment('post', 4.75);

    expect($fulfillment->hasAttribute('pickup_point'))->toBeFalse();
    expect($fulfillment->getAttribute('pickup_point'))->toBeNull();

    $fulfillment->setAttribute('pickup_point', 42);

    expect($fulfillment->hasAttribute('pickup_point'))->toBeTrue();
    expect($fulfillment->getAttribute('pickup_point'))->toBe(42);
});

it('is handed the shop storage when it is registered', function (): void {
    $storage = new InMemoryAttributeStorage();
    $shop = new Shop($storage);

    $fulfillment = new FakeFulfillment('post', 4.75);
    $shop->addFulfillment($fulfillment);

    $fulfillment->setAttribute('pickup_point', 42);

    // The value went into the shop's storage, not into a private array of the
    // fulfillment method's own.
    expect($storage->get('pickup_point'))->toBe(42);
});

it('shares one bag of attributes across methods', function (): void {
    $shop = new Shop(new InMemoryAttributeStorage());

    $post = new FakeFulfillment('post', 4.75);
    $pickup = new FakeFulfillment('pickup', 0.0);

    $shop->addFulfillment($post);
    $shop->addFulfillment($pickup);

    $post->setAttribute('note', 'leave with neighbour');

    // Preserved from the session-backed original, which kept every method's
    // attributes under one `fulfillmentAttributes` key.
    expect($pickup->getAttribute('note'))->toBe('leave with neighbour');
});

it('falls back to its own storage when nothing wires it', function (): void {
    $loose = new FakeFulfillment('post', 4.75);
    $loose->setAttribute('note', 'kept for this request only');

    expect($loose->getAttribute('note'))->toBe('kept for this request only');
    expect(
        (new FakeFulfillment('post', 4.75))->getAttribute('note')
    )->toBeNull();
});

it('refuses to read a missing required attribute', function (): void {
    $fulfillment = new FakeFulfillment('pickup', 0.0, ['pickup_point']);

    expect($fulfillment->validateAttributes())->toBeFalse();
    expect(fn() => $fulfillment->getAttribute('pickup_point'))->toThrow(
        MissingAttribute::class
    );
});

it('validates once every required attribute is set', function (): void {
    $fulfillment = new FakeFulfillment('pickup', 0.0, ['pickup_point', 'date']);

    $fulfillment->setAttribute('pickup_point', 42);
    expect($fulfillment->validateAttributes())->toBeFalse();

    $fulfillment->setAttribute('date', '2026-08-20');
    expect($fulfillment->validateAttributes())->toBeTrue();
    expect($fulfillment->getAttribute('pickup_point'))->toBe(42);
});
