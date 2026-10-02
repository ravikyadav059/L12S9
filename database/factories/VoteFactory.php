<?php

namespace Database\Factories;

use App\Models\Question;
use App\Models\User;
use App\Models\Vote;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Vote>
 */
class VoteFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'question_id' => Question::factory(),
            'answer_id' => null,
            'votable_id' => null,
            'votable_type' => null,
            'vote_type' => 'up',
        ];
    }
}
