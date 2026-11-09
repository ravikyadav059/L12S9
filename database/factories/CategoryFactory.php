<?php

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
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->unique()->words(2, true);
        $serial = fake()->unique()->numberBetween(1000, 9999);

        return [
            'category_title' => ucwords($title),
            'category_status' => 1,
            'serial_number' => $serial,
            'category_slug' => Str::slug($title).'-'.$serial,
            'category_image' => 'uploads/category/'.Str::studly($title).'.png',
        ];
    }

    /**
     * Indicate that the category is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'category_status' => 0,
        ]);
    }
}
