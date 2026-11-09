<?php

namespace Database\Factories;

use App\Models\Award;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Award>
 */
class AwardFactory extends Factory
{
    protected $model = Award::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'award_year' => (string) fake()->numberBetween(2000, 2026),
            'award_name' => fake()->words(3, true).' Award',
            'award_by' => fake()->company(),
            'status' => 1,
            'user_id' => null,
            'description' => fake()->paragraph(),
            'post_id' => null,
        ];
    }

    /**
     * Indicate that the award is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 0,
        ]);
    }
}
