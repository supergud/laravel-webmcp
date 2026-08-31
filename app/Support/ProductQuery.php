<?php

declare(strict_types=1);

namespace App\Support;

/**
 * A validated, clamped description of a catalogue search.
 *
 * Both entry points into the catalogue - the Livewire listing page and the
 * search_products WebMCP tool - build one of these. Putting the clamping here
 * rather than in either caller means an agent cannot ask for a larger page or
 * a stranger price range than a person can: there is exactly one place where
 * the limits live.
 */
final readonly class ProductQuery
{
    public const int MAX_PER_PAGE = 48;

    public const int DEFAULT_PER_PAGE = 12;

    public const int MAX_TERM_LENGTH = 100;

    public const int MAX_PRICE = 10_000_000;

    public function __construct(
        public ?string $term = null,
        public ?string $categorySlug = null,
        public ?int $minPrice = null,
        public ?int $maxPrice = null,
        public ProductSort $sort = ProductSort::Newest,
        public int $page = 1,
        public int $perPage = self::DEFAULT_PER_PAGE,
    ) {}

    /**
     * Build a query from untrusted input.
     *
     * @param  array<string, mixed>  $input
     */
    public static function fromArray(array $input): self
    {
        $min = self::intOrNull($input['min_price'] ?? null);
        $max = self::intOrNull($input['max_price'] ?? null);

        // A reversed range is a mistake rather than an attack, but silently
        // returning nothing is confusing; swapping gives the obvious result.
        if ($min !== null && $max !== null && $min > $max) {
            [$min, $max] = [$max, $min];
        }

        return new self(
            term: self::stringOrNull($input['term'] ?? null),
            categorySlug: self::stringOrNull($input['category'] ?? null),
            minPrice: $min,
            maxPrice: $max,
            sort: ProductSort::fromRequest(self::stringOrNull($input['sort'] ?? null)),
            page: max(1, (int) ($input['page'] ?? 1)),
            perPage: self::clampPerPage($input['per_page'] ?? null),
        );
    }

    public function hasTerm(): bool
    {
        return $this->term !== null;
    }

    private static function stringOrNull(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim(mb_substr($value, 0, self::MAX_TERM_LENGTH));

        return $trimmed === '' ? null : $trimmed;
    }

    private static function intOrNull(mixed $value): ?int
    {
        if ($value === null || $value === '' || ! is_numeric($value)) {
            return null;
        }

        return max(0, min(self::MAX_PRICE, (int) $value));
    }

    private static function clampPerPage(mixed $value): int
    {
        if (! is_numeric($value)) {
            return self::DEFAULT_PER_PAGE;
        }

        return max(1, min(self::MAX_PER_PAGE, (int) $value));
    }
}
