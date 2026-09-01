<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class BrandFactory extends Factory
{
    public function definition(): array
    {
        $name = $this->faker->company();
        return [
            'name' => $name,
            'slug' => Str::slug($name) . '-' . $this->faker->unique()->numberBetween(1, 1000),
            'industry' => $this->faker->word(),
            'target_audience' => 'Mọi người',
            'tone' => 'friendly',
            'is_default' => false,
            'is_active' => true,
        ];
    }
}
