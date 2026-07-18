<?php

namespace Tests\Unit;

use App\Models\Account;
use App\Models\AccountSnapshot;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Accounts\AccountBalanceCalculatorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountBalanceCalculatorServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_opening_balance_date_excludes_earlier_transactions(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create([
            'initial_balance' => '1000.00',
            'opening_balance_date' => '2026-01-01',
        ]);

        Transaction::factory()->forAccount($account)->create([
            'user_id' => $user->id,
            'transaction_date' => '2025-12-31',
            'type' => 'expense',
            'amount' => '500.00',
            'currency' => 'JPY',
        ]);
        Transaction::factory()->forAccount($account)->create([
            'user_id' => $user->id,
            'transaction_date' => '2026-01-01',
            'type' => 'expense',
            'amount' => '200.00',
            'currency' => 'JPY',
        ]);

        $service = app(AccountBalanceCalculatorService::class);

        self::assertSame('0.00', $service->calculate($account, '2025-12-31'));
        self::assertSame('800.00', $service->calculate($account, '2026-01-31'));
    }

    public function test_snapshot_balance_is_used_with_only_later_flows(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create([
            'type' => 'securities',
            'balance_method' => 'snapshot',
            'initial_balance' => '0.00',
        ]);

        Transaction::factory()->forAccount($account)->create([
            'user_id' => $user->id,
            'transaction_date' => '2026-01-15',
            'type' => 'income',
            'amount' => '500.00',
            'currency' => 'JPY',
        ]);
        AccountSnapshot::query()->create([
            'user_id' => $user->id,
            'account_id' => $account->id,
            'captured_at' => '2026-01-31 23:59:59',
            'purpose' => 'valuation',
            'balance' => '2000.00',
            'source_name' => '手動入力',
        ]);
        Transaction::factory()->forAccount($account)->create([
            'user_id' => $user->id,
            'transaction_date' => '2026-01-31',
            'type' => 'income',
            'amount' => '100.00',
            'currency' => 'JPY',
        ]);
        Transaction::factory()->forAccount($account)->create([
            'user_id' => $user->id,
            'transaction_date' => '2026-02-01',
            'type' => 'income',
            'amount' => '300.00',
            'currency' => 'JPY',
        ]);

        $balance = app(AccountBalanceCalculatorService::class)
            ->calculate($account, '2026-02-28');

        self::assertSame('2300.00', $balance);
    }

    public function test_reconciliation_snapshot_does_not_override_ledger_balance(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create([
            'balance_method' => 'snapshot',
            'initial_balance' => '100.00',
        ]);
        AccountSnapshot::query()->create([
            'user_id' => $user->id,
            'account_id' => $account->id,
            'captured_at' => '2026-01-31 23:59:59',
            'purpose' => 'reconciliation',
            'balance' => '9999.00',
            'source_name' => '照合',
        ]);

        $balance = app(AccountBalanceCalculatorService::class)
            ->calculate($account, '2026-01-31');

        self::assertSame('100.00', $balance);
    }
}
