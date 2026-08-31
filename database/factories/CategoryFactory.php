<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    protected $model = Category::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $slug = Str::slug($this->faker->unique()->slug(2));

        return [
            'slug' => $slug,
            'name' => [
                'en' => Str::headline($slug),
                'zh-TW' => '測試分類-'.$slug,
            ],
            'description' => [
                'en' => $this->faker->sentence(),
                'zh-TW' => '這是測試用的分類說明。',
            ],
            'position' => $this->faker->numberBetween(0, 100),
        ];
    }
}
