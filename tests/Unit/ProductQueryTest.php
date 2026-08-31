<?php

declare(strict_types=1);

use App\Support\ProductQuery;
use App\Support\ProductSort;

it('clamps the page size so no caller can ask for the whole catalogue', function (): void {
    expect(ProductQuery::fromArray(['per_page' => 10_000])->perPage)->toBe(ProductQuery::MAX_PER_PAGE)
        ->and(ProductQuery::fromArray(['per_page' => 0])->perPage)->toBe(1)
        ->and(ProductQuery::fromArray(['per_page' => -5])->perPage)->toBe(1)
        ->and(ProductQuery::fromArray([])->perPage)->toBe(ProductQuery::DEFAULT_PER_PAGE);
});

it('rejects a non numeric page size', function (): void {
    expect(ProductQuery::fromArray(['per_page' => 'all'])->perPage)->toBe(ProductQuery::DEFAULT_PER_PAGE);
});

it('never produces a page below one', function (): void {
    expect(ProductQuery::fromArray(['page' => -3])->page)->toBe(1)
        ->and(ProductQuery::fromArray(['page' => 0])->page)->toBe(1);
});

it('truncates an overlong search term', function (): void {
    $term = str_repeat('a', 500);

    expect(mb_strlen((string) ProductQuery::fromArray(['term' => $term])->term))
        ->toBe(ProductQuery::MAX_TERM_LENGTH);
});

it('treats blank and whitespace terms as absent', function (mixed $value): void {
    expect(ProductQuery::fromArray(['term' => $value])->hasTerm())->toBeFalse();
})->with([
    'empty string' => '',
    'spaces' => '   ',
    'null' => null,
    'array' => [[['not', 'a', 'string']]],
    'integer' => 42,
]);

it('clamps prices into a sane range', function (): void {
    expect(ProductQuery::fromArray(['min_price' => -100])->minPrice)->toBe(0)
        ->and(ProductQuery::fromArray(['max_price' => 999_999_999])->maxPrice)->toBe(ProductQuery::MAX_PRICE);
});

it('ignores non numeric prices', function (): void {
    $query = ProductQuery::fromArray(['min_price' => 'cheap', 'max_price' => '']);

    expect($query->minPrice)->toBeNull()
        ->and($query->maxPrice)->toBeNull();
});

it('swaps a reversed price range', function (): void {
    $query = ProductQuery::fromArray(['min_price' => 900, 'max_price' => 100]);

    expect($query->minPrice)->toBe(100)
        ->and($query->maxPrice)->toBe(900);
});

it('falls back to the default sort for anything unrecognised', function (mixed $value): void {
    expect(ProductQuery::fromArray(['sort' => $value])->sort)->toBe(ProductSort::default());
})->with([
    'unknown' => 'cheapest',
    'sql fragment' => 'price asc; drop table products',
    'null' => null,
    'wrong type' => 99,
]);

it('accepts every published sort value', function (string $value): void {
    expect(ProductQuery::fromArray(['sort' => $value])->sort->value)->toBe($value);
})->with(fn () => ProductSort::values());
