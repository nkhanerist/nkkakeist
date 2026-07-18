<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\User;
use Illuminate\Database\Seeder;

class AccountSeeder extends Seeder
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

        Account::factory()->for($user)->createMany([
            [
                'name' => 'Wallet',
                'type' => 'cash',
                'currency' => 'JPY',
                'initial_balance' => 20000,
                'display_order' => 1,
                'is_active' => true,
                'note' => '日常的に使う現金口座です。',
            ],
            [
                'name' => 'Main Bank',
                'type' => 'bank',
                'currency' => 'JPY',
                'initial_balance' => 250000,
                'display_order' => 2,
                'is_active' => true,
                'note' => '生活費の入出金用です。',
            ],
        ]);
    }
}
