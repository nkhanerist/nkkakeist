<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    protected $model = Category::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->unique()->word(),
            'type' => fake()->randomElement(Category::types()),
            'color' => fake()->optional()->hexColor(),
            'icon' => fake()->optional()->slug(1),
            'display_order' => fake()->numberBetween(0, 20),
            'is_active' => true,
        ];
    }
}
