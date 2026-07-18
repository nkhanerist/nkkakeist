<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountSnapshot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AccountSnapshotTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_record_and_view_a_valuation_snapshot(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create([
            'name' => 'THEO',
            'type' => 'securities',
            'balance_method' => 'snapshot',
        ]);

        $this->actingAs($user)
            ->post(route('accounts.snapshots.store', $account), [
                'balance_date' => '2026-07-18',
                'balance' => '612345.67',
                'source_name' => 'THEOマイページ',
                'note' => '営業日終了後',
            ])
            ->assertRedirect(route('accounts.snapshots.index', $account));

        $snapshot = AccountSnapshot::query()->sole();

        self::assertSame($user->id, $snapshot->user_id);
        self::assertSame($account->id, $snapshot->account_id);
        self::assertSame('valuation', $snapshot->purpose);
        self::assertSame('612345.67', (string) $snapshot->balance);
        self::assertSame('2026-07-18', $snapshot->captured_at->toDateString());

        $this->actingAs($user)
            ->get(route('accounts.snapshots.index', $account))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Accounts/Snapshots/Index')
                ->where('account.id', $account->id)
                ->where('account.has_valuation_snapshot', true)
                ->where('account.current_balance', '612345.67')
                ->has('snapshots', 1)
                ->where('snapshots.0.balance_date', '2026-07-18')
                ->where('snapshots.0.balance', '612345.67')
                ->where('snapshots.0.source_name', 'THEOマイページ'));
    }

    public function test_recording_the_same_date_updates_the_existing_snapshot(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create([
            'balance_method' => 'snapshot',
        ]);

        $payload = [
            'balance_date' => '2026-07-18',
            'balance' => '1000.00',
            'source_name' => '手動入力',
            'note' => null,
        ];

        $this->actingAs($user)
            ->post(route('accounts.snapshots.store', $account), $payload)
            ->assertRedirect(route('accounts.snapshots.index', $account));

        $payload['balance'] = '1200.00';
        $payload['source_name'] = '更新値';

        $this->actingAs($user)
            ->post(route('accounts.snapshots.store', $account), $payload)
            ->assertRedirect(route('accounts.snapshots.index', $account));

        self::assertSame(1, AccountSnapshot::query()->count());
        $this->assertDatabaseHas('account_snapshots', [
            'account_id' => $account->id,
            'balance' => '1200.00',
            'source_name' => '更新値',
        ]);
    }

    public function test_user_can_edit_and_delete_a_valuation_snapshot(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create([
            'balance_method' => 'snapshot',
        ]);
        $snapshot = AccountSnapshot::query()->create([
            'user_id' => $user->id,
            'account_id' => $account->id,
            'captured_at' => '2026-07-17 23:59:59',
            'purpose' => 'valuation',
            'balance' => '1000.00',
            'source_name' => '手動入力',
        ]);

        $this->actingAs($user)
            ->put(route('accounts.snapshots.update', [$account, $snapshot]), [
                'balance_date' => '2026-07-18',
                'balance' => '1300.00',
                'source_name' => 'THEOマイページ',
                'note' => '修正',
            ])
            ->assertRedirect(route('accounts.snapshots.index', $account));

        $this->assertDatabaseHas('account_snapshots', [
            'id' => $snapshot->id,
            'balance' => '1300.00',
            'source_name' => 'THEOマイページ',
            'note' => '修正',
        ]);

        $this->actingAs($user)
            ->delete(route('accounts.snapshots.destroy', [$account, $snapshot]))
            ->assertRedirect(route('accounts.snapshots.index', $account));

        $this->assertDatabaseMissing('account_snapshots', [
            'id' => $snapshot->id,
        ]);
    }

    public function test_ledger_account_cannot_receive_a_valuation_snapshot(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create([
            'balance_method' => 'ledger',
        ]);

        $this->actingAs($user)
            ->post(route('accounts.snapshots.store', $account), [
                'balance_date' => '2026-07-18',
                'balance' => '1000.00',
                'source_name' => '手動入力',
            ])
            ->assertSessionHasErrors('balance');

        $this->assertDatabaseCount('account_snapshots', 0);
    }

    public function test_user_cannot_access_another_users_snapshots(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $otherAccount = Account::factory()->for($otherUser)->create([
            'balance_method' => 'snapshot',
        ]);

        $this->actingAs($user)
            ->get(route('accounts.snapshots.index', $otherAccount))
            ->assertForbidden();

        $this->actingAs($user)
            ->post(route('accounts.snapshots.store', $otherAccount), [
                'balance_date' => '2026-07-18',
                'balance' => '1000.00',
                'source_name' => '手動入力',
            ])
            ->assertForbidden();
    }
}
