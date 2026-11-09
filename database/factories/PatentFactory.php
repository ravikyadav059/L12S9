<?php

namespace Database\Factories;

use App\Models\Patent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Patent>
 */
class PatentFactory extends Factory
{
    protected $model = Patent::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(5),
            'inventor' => fake()->name(),
            'assignee' => fake()->company(),
            'application_no' => (string) fake()->numberBetween(10000000, 99999999),
            'patent_no' => (string) fake()->numberBetween(100000, 999999),
            'country' => '101',
            'category_id' => null,
            'filing_date' => fake()->monthName().' '.fake()->year(),
            'publication_date' => fake()->monthName().' '.fake()->year(),
            'status' => 1,
            'description' => fake()->paragraph(),
            'user_id' => null,
        ];
    }

    /**
     * Indicate that the patent is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 0,
        ]);
    }
}
