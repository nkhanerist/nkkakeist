<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountSnapshot;
use App\Models\Import;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Accounts\AccountBalanceCalculatorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AccountReconciliationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_view_their_reconcilable_snapshot_and_clearing_accounts(): void
    {
        $user = User::factory()->create();
        Account::factory()->for($user)->create([
            'name' => 'Main Bank',
            'balance_role' => 'asset',
            'balance_method' => 'ledger',
        ]);
        Account::factory()->for($user)->create([
            'name' => 'Investment',
            'type' => 'securities',
            'balance_role' => 'asset',
            'balance_method' => 'snapshot',
        ]);
        Account::factory()->for($user)->create([
            'name' => 'Clearing',
            'balance_role' => 'clearing',
            'balance_method' => 'ledger',
        ]);
        Account::factory()->for(User::factory()->create())->create([
            'name' => 'Other User Bank',
        ]);

        $this->actingAs($user)
            ->get(route('accounts.reconciliation.index', [
                'balance_date' => '2026-07-18',
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Accounts/Reconciliation/Index')
                ->where('balanceDate', '2026-07-18')
                ->has('reconcilableAccounts', 1)
                ->where('reconcilableAccounts.0.name', 'Main Bank')
                ->has('snapshotAccounts', 1)
                ->where('snapshotAccounts.0.name', 'Investment')
                ->has('clearingAccounts', 1)
                ->where('clearingAccounts.0.name', 'Clearing'));
    }

    public function test_reconciliation_page_shows_the_latest_imported_official_balance(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create([
            'name' => 'dカード',
            'type' => 'credit_card',
            'balance_role' => 'liability',
            'balance_method' => 'ledger',
            'initial_balance' => '0.00',
        ]);
        AccountSnapshot::query()->create([
            'user_id' => $user->id,
            'account_id' => $account->id,
            'captured_at' => '2026-07-18 23:59:59',
            'purpose' => 'official_balance',
            'balance' => '-65940.00',
            'source_name' => 'Money Forward',
            'metadata' => [
                'next_payment_amount' => '42000.00',
                'next_payment_date' => '2026-08-10',
            ],
        ]);

        $this->actingAs($user)
            ->get(route('accounts.reconciliation.index', [
                'balance_date' => '2026-07-18',
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('reconcilableAccounts.0.latest_official_balance_date', '2026-07-18')
                ->where('reconcilableAccounts.0.latest_official_balance', '-65940.00')
                ->where('reconcilableAccounts.0.latest_official_balance_source', 'Money Forward')
                ->where('reconcilableAccounts.0.next_payment_amount', '42000.00')
                ->where('reconcilableAccounts.0.next_payment_date', '2026-08-10'));
    }

    public function test_user_can_reconcile_actual_balance_into_the_opening_balance(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create([
            'name' => 'Main Bank',
            'type' => 'bank',
            'balance_role' => 'asset',
            'balance_method' => 'ledger',
            'initial_balance' => '1000.00',
            'opening_balance_date' => null,
        ]);
        Transaction::factory()->forAccount($account)->create([
            'transaction_date' => '2026-01-10',
            'type' => 'expense',
            'amount' => '300.00',
            'currency' => 'JPY',
        ]);

        $this->actingAs($user)
            ->post(route('accounts.reconciliation.store', $account), [
                'balance_date' => '2026-07-18',
                'actual_balance' => '900.00',
                'source_name' => '銀行アプリ',
                'note' => '取込済みを確認',
                'confirmed' => true,
            ])
            ->assertRedirect(route('accounts.reconciliation.index', [
                'balance_date' => '2026-07-18',
            ]));

        $account->refresh();
        self::assertSame('1200.00', (string) $account->initial_balance);
        self::assertSame('2026-01-10', $account->opening_balance_date?->toDateString());
        self::assertSame(
            '900.00',
            app(AccountBalanceCalculatorService::class)->calculate($account, '2026-07-18'),
        );

        $snapshot = AccountSnapshot::query()->sole();
        self::assertSame('reconciliation', $snapshot->purpose);
        self::assertSame('900.00', (string) $snapshot->balance);
        self::assertSame('銀行アプリ', $snapshot->source_name);
        self::assertSame('200.00', $snapshot->metadata['difference']);
        self::assertSame('1200.00', $snapshot->metadata['updated_initial_balance']);
    }

    public function test_reconciling_the_same_date_updates_the_audit_snapshot(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create([
            'balance_role' => 'asset',
            'balance_method' => 'ledger',
            'initial_balance' => '0.00',
        ]);

        foreach (['100.00', '120.00'] as $actualBalance) {
            $this->actingAs($user)
                ->post(route('accounts.reconciliation.store', $account), [
                    'balance_date' => '2026-07-18',
                    'actual_balance' => $actualBalance,
                    'source_name' => '手動照合',
                    'confirmed' => true,
                ])
                ->assertSessionHasNoErrors();
        }

        self::assertSame(1, AccountSnapshot::query()->count());
        self::assertSame('120.00', (string) AccountSnapshot::query()->sole()->balance);
        self::assertSame('120.00', (string) $account->refresh()->initial_balance);
    }

    public function test_manual_reconciliation_does_not_overwrite_an_import_snapshot(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create([
            'balance_role' => 'asset',
            'balance_method' => 'ledger',
            'initial_balance' => '0.00',
        ]);
        $import = Import::query()->create([
            'user_id' => $user->id,
            'account_id' => $account->id,
            'source_name' => 'jre_point',
            'original_filename' => 'pointlog.html',
            'storage_path' => 'imports/pointlog.html',
            'status' => 'imported',
        ]);
        $importSnapshot = AccountSnapshot::query()->create([
            'user_id' => $user->id,
            'account_id' => $account->id,
            'import_id' => $import->id,
            'captured_at' => '2026-07-18 23:59:59',
            'purpose' => 'reconciliation',
            'balance' => '90.00',
            'source_name' => 'JRE POINT',
            'metadata' => ['initial_balance_rebased' => true],
        ]);

        $this->actingAs($user)
            ->post(route('accounts.reconciliation.store', $account), [
                'balance_date' => '2026-07-18',
                'actual_balance' => '100.00',
                'source_name' => '手動照合',
                'confirmed' => true,
            ])
            ->assertSessionHasNoErrors();

        self::assertSame(2, AccountSnapshot::query()->count());
        self::assertSame('90.00', (string) $importSnapshot->refresh()->balance);
        self::assertTrue($importSnapshot->metadata['initial_balance_rebased']);
        $this->assertDatabaseHas('account_snapshots', [
            'account_id' => $account->id,
            'import_id' => null,
            'purpose' => 'reconciliation',
            'balance' => '100.00',
            'source_name' => '手動照合',
        ]);
    }

    public function test_snapshot_and_clearing_accounts_cannot_be_reconciled(): void
    {
        $user = User::factory()->create();
        $snapshotAccount = Account::factory()->for($user)->create([
            'balance_role' => 'asset',
            'balance_method' => 'snapshot',
            'initial_balance' => '0.00',
        ]);
        $clearingAccount = Account::factory()->for($user)->create([
            'balance_role' => 'clearing',
            'balance_method' => 'ledger',
            'initial_balance' => '0.00',
        ]);
        $payload = [
            'balance_date' => '2026-07-18',
            'actual_balance' => '100.00',
            'source_name' => '手動照合',
            'confirmed' => true,
        ];

        $this->actingAs($user)
            ->post(route('accounts.reconciliation.store', $snapshotAccount), $payload)
            ->assertSessionHasErrors('actual_balance');
        $this->actingAs($user)
            ->post(route('accounts.reconciliation.store', $clearingAccount), $payload)
            ->assertSessionHasErrors('actual_balance');

        self::assertSame('0.00', (string) $snapshotAccount->refresh()->initial_balance);
        self::assertSame('0.00', (string) $clearingAccount->refresh()->initial_balance);
        $this->assertDatabaseCount('account_snapshots', 0);
    }

    public function test_user_cannot_reconcile_another_users_account(): void
    {
        $user = User::factory()->create();
        $otherAccount = Account::factory()->for(User::factory()->create())->create([
            'balance_role' => 'asset',
            'balance_method' => 'ledger',
        ]);

        $this->actingAs($user)
            ->post(route('accounts.reconciliation.store', $otherAccount), [
                'balance_date' => '2026-07-18',
                'actual_balance' => '100.00',
                'source_name' => '手動照合',
                'confirmed' => true,
            ])
            ->assertForbidden();
    }

    public function test_reconciliation_requires_explicit_confirmation(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create([
            'balance_role' => 'asset',
            'balance_method' => 'ledger',
            'initial_balance' => '0.00',
        ]);

        $this->actingAs($user)
            ->post(route('accounts.reconciliation.store', $account), [
                'balance_date' => '2026-07-18',
                'actual_balance' => '100.00',
                'source_name' => '手動照合',
            ])
            ->assertSessionHasErrors('confirmed');

        self::assertSame('0.00', (string) $account->refresh()->initial_balance);
    }
}
