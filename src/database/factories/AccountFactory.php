<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Account>
 */
class AccountFactory extends Factory
{
    protected $model = Account::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->unique()->company().' Account',
            'type' => fake()->randomElement(Account::types()),
            'balance_role' => 'asset',
            'balance_method' => 'ledger',
            'include_in_net_worth' => true,
            'currency' => 'JPY',
            'initial_balance' => fake()->randomFloat(2, 0, 500000),
            'opening_balance_date' => null,
            'display_order' => fake()->numberBetween(0, 20),
            'is_active' => true,
            'note' => fake()->optional()->sentence(),
        ];
    }
}
