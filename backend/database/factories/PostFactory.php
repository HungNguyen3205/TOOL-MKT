<?php

namespace Database\Factories;

use App\Models\Post;
use Illuminate\Database\Eloquent\Factories\Factory;

class PostFactory extends Factory
{
    protected $model = Post::class;

    public function definition(): array
    {
        return [
            'title' => $this->faker->sentence,
            'content' => $this->faker->paragraphs(3, true),
            'cta' => $this->faker->sentence,
            'hashtags' => ['#' . $this->faker->word, '#' . $this->faker->word],
            'objective' => 'sales',
            'tone' => 'friendly',
            'content_length' => 'short',
            'source' => 'manual',
            'status' => 'draft',
        ];
    }
}
