<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Seeder;

class TransactionSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::where('email', 'developer@example.test')->first();

        if (! $user instanceof User) {
            return;
        }

        $wallet = Account::where('user_id', $user->id)->where('name', 'Wallet')->first();
        $bank = Account::where('user_id', $user->id)->where('name', 'Main Bank')->first();
        $food = Category::where('user_id', $user->id)->where('name', 'Food')->first();
        $salary = Category::where('user_id', $user->id)->where('name', 'Salary')->first();

        if (! $wallet instanceof Account || ! $bank instanceof Account) {
            return;
        }

        if ($food instanceof Category) {
            Transaction::factory()
                ->forAccount($wallet)
                ->forCategory($food, $food->subcategories()->where('name', 'Groceries')->first())
                ->create([
                    'transaction_date' => now()->subDays(2)->toDateString(),
                    'type' => 'expense',
                    'amount' => 4200,
                    'merchant_name' => 'Local Supermarket',
                    'description' => '週末の食材購入',
                    'memo' => 'ポイント利用あり',
                ]);
        }

        if ($salary instanceof Category) {
            Transaction::factory()
                ->forAccount($bank)
                ->forCategory($salary, $salary->subcategories()->where('name', 'Monthly Salary')->first())
                ->create([
                    'transaction_date' => now()->startOfMonth()->toDateString(),
                    'type' => 'income',
                    'amount' => 280000,
                    'merchant_name' => 'Employer Inc.',
                    'description' => '月給',
                    'payment_method_label' => '銀行振込',
                ]);
        }

        Transaction::factory()
            ->transfer($bank, $wallet)
            ->create([
                'transaction_date' => now()->subDay()->toDateString(),
                'amount' => 30000,
                'description' => '生活費引き出し',
            ]);
    }
}
