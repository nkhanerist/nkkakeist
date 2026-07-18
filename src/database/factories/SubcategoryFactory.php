<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Subcategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Subcategory>
 */
class SubcategoryFactory extends Factory
{
    protected $model = Subcategory::class;

    public function definition(): array
    {
        return [
            'user_id' => null,
            'category_id' => Category::factory(),
            'name' => fake()->unique()->word(),
            'display_order' => fake()->numberBetween(0, 20),
            'is_active' => true,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (Subcategory $subcategory): void {
            if ($subcategory->category !== null) {
                $subcategory->user_id = $subcategory->category->user_id;
            }
        })->afterCreating(function (Subcategory $subcategory): void {
            if ($subcategory->category !== null && $subcategory->user_id !== $subcategory->category->user_id) {
                $subcategory->forceFill([
                    'user_id' => $subcategory->category->user_id,
                ])->save();
            }
        });
    }

    public function forCategory(Category $category): static
    {
        return $this->state(fn (): array => [
            'user_id' => $category->user_id,
            'category_id' => $category->id,
        ]);
    }
}
