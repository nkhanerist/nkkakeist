<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\Category;
use App\Models\Subcategory;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Transaction>
 */
class TransactionFactory extends Factory
{
    protected $model = Transaction::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'account_id' => function (array $attributes): int {
                return Account::factory()->create([
                    'user_id' => $attributes['user_id'],
                ])->id;
            },
            'transfer_account_id' => null,
            'transaction_date' => fake()->date(),
            'posted_at' => null,
            'type' => 'expense',
            'amount' => fake()->randomFloat(2, 100, 30000),
            'currency' => 'JPY',
            'merchant_name' => fake()->optional()->company(),
            'description' => fake()->optional()->sentence(),
            'category_id' => function (array $attributes): int {
                return Category::factory()->create([
                    'user_id' => $attributes['user_id'],
                    'type' => 'expense',
                ])->id;
            },
            'subcategory_id' => null,
            'payment_method_label' => fake()->optional()->randomElement(['現金', 'カード', '振込']),
            'external_id' => null,
            'import_id' => null,
            'import_row_id' => null,
            'duplicate_hash' => null,
            'is_confirmed' => true,
            'is_calculation_target' => true,
            'affects_account_balance' => fn (array $attributes): bool => ($attributes['type'] ?? 'expense') === 'transfer'
                || (bool) ($attributes['is_calculation_target'] ?? true),
            'memo' => fake()->optional()->sentence(),
        ];
    }

    public function income(): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => 'income',
            'category_id' => Category::factory()->create([
                'user_id' => $attributes['user_id'],
                'type' => 'income',
            ])->id,
        ]);
    }

    public function transfer(Account $fromAccount, Account $toAccount): static
    {
        return $this->state(fn (): array => [
            'user_id' => $fromAccount->user_id,
            'account_id' => $fromAccount->id,
            'transfer_account_id' => $toAccount->id,
            'type' => 'transfer',
            'category_id' => null,
            'subcategory_id' => null,
        ]);
    }

    public function forAccount(Account $account): static
    {
        return $this->state(fn (): array => [
            'user_id' => $account->user_id,
            'account_id' => $account->id,
        ]);
    }

    public function forCategory(Category $category, ?Subcategory $subcategory = null): static
    {
        return $this->state(fn (): array => [
            'user_id' => $category->user_id,
            'category_id' => $category->id,
            'subcategory_id' => $subcategory?->id,
        ]);
    }
}
