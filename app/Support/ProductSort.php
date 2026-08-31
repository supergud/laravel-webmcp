<?php

declare(strict_types=1);

namespace App\Support;

/**
 * The orderings the catalogue can be sorted by.
 *
 * This is a closed set on purpose: the value arrives from a query string and
 * from the search_products WebMCP tool, and it ends up in an ORDER BY clause.
 * Modelling it as an enum means an unknown value can never reach the query
 * builder, and the same list can be published as the tool's schema.
 */
enum ProductSort: string
{
    case Newest = 'newest';
    case PriceAsc = 'price_asc';
    case PriceDesc = 'price_desc';
    case Name = 'name';

    public static function default(): self
    {
        return self::Newest;
    }

    public static function fromRequest(?string $value): self
    {
        return self::tryFrom($value ?? '') ?? self::default();
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $case): string => $case->value, self::cases());
    }

    /**
     * The column and direction this sort maps to.
     *
     * @return array{string, 'asc'|'desc'}
     */
    public function toOrderBy(string $locale): array
    {
        return match ($this) {
            self::Newest => ['id', 'desc'],
            self::PriceAsc => ['price', 'asc'],
            self::PriceDesc => ['price', 'desc'],
            self::Name => ["name->{$locale}", 'asc'],
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Newest => __('shop.products.sort_newest'),
            self::PriceAsc => __('shop.products.sort_price_asc'),
            self::PriceDesc => __('shop.products.sort_price_desc'),
            self::Name => __('shop.products.sort_name'),
        };
    }
}
