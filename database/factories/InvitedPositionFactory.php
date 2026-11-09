<?php

namespace Database\Factories;

use App\Models\InvitedPosition;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InvitedPosition>
 */
class InvitedPositionFactory extends Factory
{
    protected $model = InvitedPosition::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'role' => fake()->randomElement(['Keynote Speaker', 'Visiting Professor', 'Industry Expert', 'Resource Person', 'Guest Lecturer']),
            'join_date' => (string) fake()->numberBetween(2015, 2022),
            'end_date' => (string) fake()->numberBetween(2023, 2026),
            'continue_invited_position' => fake()->randomElement([0, 1]),
            'webiste_url' => fake()->url(),
            'organization_id' => null,
            'country' => '101',
            'user_id' => null,
            'status' => 1,
        ];
    }

    /**
     * Indicate that the invited position is continuing / ongoing.
     */
    public function continuing(): static
    {
        return $this->state(fn (array $attributes) => [
            'continue_invited_position' => 1,
            'end_date' => '',
        ]);
    }

    /**
     * Indicate that the invited position is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 0,
        ]);
    }
}

