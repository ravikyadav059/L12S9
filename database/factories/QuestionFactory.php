<?php

namespace Database\Factories;

use App\Models\Question;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Question>
 */
class QuestionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->sentence();

        return [
            'user_id' => User::factory(),
            'title' => $title,
            'slug' => Str::slug($title),
            'description' => fake()->paragraphs(3, true),
            'tags' => fake()->words(3),
            'thumbnail' => fake()->imageUrl(),
            'views_count' => 0,
            'votes_count' => 0,
            'shared_count' => 0,
            'answer_count' => 0,
            'likes_count' => 0,
            'is_closed' => false,
            'status' => 1,
            'follow' => [],
            'save' => [],
            'is_reported' => false,
        ];
    }
}
