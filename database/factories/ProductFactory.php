<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $slug = Str::slug($this->faker->unique()->slug(3));

        return [
            'category_id' => Category::factory(),
            'sku' => strtoupper(Str::random(3)).'-'.$this->faker->unique()->numberBetween(1000, 9999),
            'slug' => $slug,
            'name' => [
                'en' => Str::headline($slug),
                'zh-TW' => '測試商品-'.$slug,
            ],
            'description' => [
                'en' => $this->faker->sentence(),
                'zh-TW' => '這是測試用的商品說明。',
            ],
            'price' => $this->faker->numberBetween(390, 89900),
            'stock' => $this->faker->numberBetween(1, 50),
            'is_active' => true,
        ];
    }

    public function outOfStock(): self
    {
        return $this->state(fn (): array => ['stock' => 0]);
    }

    public function inactive(): self
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }
}
