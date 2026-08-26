<?php

declare(strict_types=1);

/*
 * The canonical form the whole options design leans on.
 *
 * Both storages merge lines, the repository queries, and Order::add() freezes
 * on the string LineOptions::canonical() produces, so the properties below —
 * one selection, one string; empty is null, not '[]'; decoding never throws —
 * are what everything in CartLineOptionsTest is built out of. They are pinned
 * down here on the class itself, where a failure says which property broke
 * rather than which cart behaviour noticed.
 */

use Tnt\Ecommerce\Cart\LineOptions;

it('encodes no options as null', function (): void {
    // NULL is what every line from before the column existed holds, so the
    // empty selection has to be NULL too or old lines and new no-options
    // lines would never merge. '[]' would be a second spelling of nothing.
    expect(LineOptions::canonical([]))->toBeNull();
});

it('encodes the same selection identically whatever its order', function (): void {
    $canonical = LineOptions::canonical([
        'size' => 'L',
        'extras' => ['sauce' => 'yes', 'bread' => 'no'],
    ]);

    // Key order sorted away at every level: assembled backwards, nested level
    // included, it is byte-for-byte the same string — which is what makes it
    // usable as a merge key at all.
    expect(
        LineOptions::canonical([
            'extras' => ['bread' => 'no', 'sauce' => 'yes'],
            'size' => 'L',
        ])
    )->toBe($canonical);
});

it('tells different selections apart', function (): void {
    expect(LineOptions::canonical(['cheese' => 'no goat']))->not->toBe(
        LineOptions::canonical(['cheese' => 'no blue'])
    );
});

it('keeps the order of a list', function (): void {
    // Keys are sorted; values are not. For a list the order is the value — a
    // ranking, a sequence — and this class cannot tell a set from a sequence.
    // A shop that means a set keys the array or sorts it itself; the docblock
    // on LineOptions says so.
    expect(LineOptions::canonical(['steps' => ['a', 'b']]))->not->toBe(
        LineOptions::canonical(['steps' => ['b', 'a']])
    );
});

it('decodes what it encoded', function (): void {
    $options = ['size' => 'L', 'extras' => ['bread' => 'no']];

    // Every value survives the round trip; the key order comes back
    // *canonical* — sorted — rather than as the caller assembled it, because
    // the canonical string is all that was stored. Key order was never part
    // of the selection: that is the whole premise of canonicalising.
    expect(LineOptions::decode(LineOptions::canonical($options)))->toBe([
        'extras' => ['bread' => 'no'],
        'size' => 'L',
    ]);
});

it('decodes the empty column as no options', function (): void {
    // NULL is a line from before options existed as much as one added
    // without any; both are the same absence of choices.
    expect(LineOptions::decode(null))->toBe([]);
    expect(LineOptions::decode(''))->toBe([]);
});

it('decodes a column that is not JSON as no options', function (): void {
    // decode() reads a database column, and a column can be hand-edited.
    // "No options" is an answer every reader already handles; an exception
    // would make one edited row unrenderable.
    expect(LineOptions::decode('not json'))->toBe([]);
    expect(LineOptions::decode('"a bare string"'))->toBe([]);
    expect(LineOptions::decode('42'))->toBe([]);
});

it('refuses a value JSON cannot carry', function (): void {
    // The asymmetry with decode() is deliberate: canonical() runs on the way
    // *in*, where an unencodable option is a bug in the shop worth hearing
    // about, not a line worth silently mangling.
    LineOptions::canonical(['rate' => INF]);
})->throws(JsonException::class);
