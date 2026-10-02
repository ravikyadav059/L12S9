<?php

namespace Database\Factories;

use App\Models\Answer;
use App\Models\Question;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Answer>
 */
class AnswerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'question_id' => Question::factory(),
            'user_id' => User::factory(),
            'files' => [],
            'content' => fake()->paragraph(),
            'votes_count' => 0,
            'is_accepted' => false,
            'status' => 1,
            'follow' => [],
            'save' => [],
        ];
    }
}
