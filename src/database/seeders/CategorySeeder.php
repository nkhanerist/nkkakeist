<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\User;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $user = User::where('email', 'developer@example.test')->first();

        if (! $user instanceof User) {
            return;
        }

        $food = Category::factory()->for($user)->create([
            'name' => 'Food',
            'type' => 'expense',
            'color' => '#f97316',
            'icon' => 'utensils',
            'display_order' => 1,
            'is_active' => true,
        ]);

        $salary = Category::factory()->for($user)->create([
            'name' => 'Salary',
            'type' => 'income',
            'color' => '#10b981',
            'icon' => 'briefcase',
            'display_order' => 2,
            'is_active' => true,
        ]);

        $food->subcategories()->createMany([
            [
                'user_id' => $user->id,
                'name' => 'Groceries',
                'display_order' => 1,
                'is_active' => true,
            ],
            [
                'user_id' => $user->id,
                'name' => 'Dining Out',
                'display_order' => 2,
                'is_active' => true,
            ],
        ]);

        $salary->subcategories()->createMany([
            [
                'user_id' => $user->id,
                'name' => 'Monthly Salary',
                'display_order' => 1,
                'is_active' => true,
            ],
        ]);
    }
}
