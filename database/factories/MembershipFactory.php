<?php

namespace Database\Factories;

use App\Models\Membership;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Membership>
 */
class MembershipFactory extends Factory
{
    protected $model = Membership::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'membership_type' => fake()->randomElement(['IEEE Senior Member', 'ACM Professional Member', 'Associate Membership', 'Life Member']),
            'join_date' => (string) fake()->numberBetween(2010, 2022),
            'end_date' => (string) fake()->numberBetween(2023, 2026),
            'continue_membership' => fake()->randomElement([0, 1]),
            'webiste_url' => fake()->url(),
            'organization_id' => null,
            'user_id' => null,
            'country' => '101',
            'status' => 1,
        ];
    }

    /**
     * Indicate that the membership is continuing / ongoing.
     */
    public function continuing(): static
    {
        return $this->state(fn (array $attributes) => [
            'continue_membership' => 1,
            'end_date' => '',
        ]);
    }

    /**
     * Indicate that the membership is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 0,
        ]);
    }
}
