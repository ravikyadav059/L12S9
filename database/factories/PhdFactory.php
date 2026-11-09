<?php

namespace Database\Factories;

use App\Models\Phd;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Phd>
 */
class PhdFactory extends Factory
{
    protected $model = Phd::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'phd_name' => fake()->name(),
            'phd_title' => fake()->sentence(4),
            'thesis_type' => fake()->randomElement(['PhD Thesis', 'BE Project', 'Case Study', 'Research Study']),
            'research_area' => fake()->words(3, true),
            'institution_id' => null,
            'status' => 1,
            'phd_status' => fake()->randomElement(['Ongoing', 'Completed']),
            'user_id' => null,
        ];
    }

    /**
     * Indicate that the PhD thesis is completed.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'phd_status' => 'Completed',
        ]);
    }

    /**
     * Indicate that the PhD thesis is ongoing.
     */
    public function ongoing(): static
    {
        return $this->state(fn (array $attributes) => [
            'phd_status' => 'Ongoing',
        ]);
    }

    /**
     * Indicate that the PhD record is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 0,
        ]);
    }
}
