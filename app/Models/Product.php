<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Translatable\HasTranslations;

/**
 * @property int $id
 * @property int $category_id
 * @property string $sku
 * @property string $slug
 * @property int $price
 * @property int $stock
 * @property bool $is_active
 * @property-read Category $category
 */
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    use HasTranslations;

    /** @var list<string> */
    public array $translatable = ['name', 'description'];

    /** @var list<string> */
    protected $fillable = ['category_id', 'sku', 'slug', 'name', 'description', 'price', 'stock', 'is_active'];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function isInStock(): bool
    {
        return $this->stock > 0;
    }

    /**
     * Only products a customer is allowed to see or buy.
     *
     * Every public query and every WebMCP tool goes through this, so an
     * inactive product can never be surfaced or added to a cart.
     *
     * @param  Builder<Product>  $query
     */
    public function scopeAvailable(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * Case-insensitive search across the translated name and description.
     *
     * The locale is interpolated into a JSON path, so it must already have
     * been through App\Support\Locales - callers pass App::getLocale(), which
     * SetLocale guarantees is whitelisted.
     *
     * @param  Builder<Product>  $query
     */
    public function scopeSearch(Builder $query, string $term, string $locale): void
    {
        // SQLite's LIKE has no escape character unless one is declared with an
        // ESCAPE clause, so a backslash would not neutralise anything here.
        // Stripping the wildcards instead keeps this free of raw SQL.
        $needle = trim(str_replace(['%', '_'], '', $term));

        // A term made only of wildcards is not a query. Returning nothing stops
        // it being used to dump the catalogue in one call.
        if ($needle === '') {
            $query->whereIn('id', []);

            return;
        }

        $like = '%'.$needle.'%';

        $query->where(function (Builder $query) use ($like, $locale): void {
            $query->where("name->{$locale}", 'like', $like)
                ->orWhere("description->{$locale}", 'like', $like)
                ->orWhere('sku', 'like', $like);
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'stock' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
