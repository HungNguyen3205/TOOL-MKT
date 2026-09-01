<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Brand;

class ContentTemplateFactory extends Factory
{
    public function definition(): array
    {
        return [
            'brand_id' => Brand::factory(),
            'name' => 'Template ' . $this->faker->word(),
            'objective' => 'sales',
            'is_default' => false,
            'is_active' => true,
        ];
    }
}
