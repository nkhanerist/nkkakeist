<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Category;
use App\Models\Import;
use App\Models\ImportRow;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AccountCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_view_only_their_own_accounts(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $ownAccount = Account::factory()->for($user)->create([
            'name' => 'My Wallet',
            'display_order' => 1,
        ]);

        Account::factory()->for($otherUser)->create([
            'name' => 'Other Bank',
            'display_order' => 1,
        ]);

        $this->actingAs($user)
            ->get(route('accounts.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Accounts/Index')
                ->has('accounts', 1)
                ->where('accounts.0.id', $ownAccount->id)
                ->where('accounts.0.name', 'My Wallet'));
    }

    public function test_authenticated_user_can_create_an_account(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('accounts.store'), [
            'name' => 'Main Bank',
            'type' => 'bank',
            'balance_role' => 'asset',
            'balance_method' => 'ledger',
            'include_in_net_worth' => false,
            'monthly_close_required' => true,
            'currency' => 'jpy',
            'initial_balance' => '120000.50',
            'opening_balance_date' => '2026-01-01',
            'display_order' => 2,
            'is_active' => true,
            'note' => '生活費用のメイン口座',
            'import_aliases' => "住信SBI\nSBIハイブリッド",
        ]);

        $response->assertRedirect(route('accounts.index'));

        $this->assertDatabaseHas('accounts', [
            'user_id' => $user->id,
            'name' => 'Main Bank',
            'type' => 'bank',
            'balance_role' => 'asset',
            'balance_method' => 'ledger',
            'include_in_net_worth' => 0,
            'monthly_close_required' => 1,
            'currency' => 'JPY',
            'display_order' => 2,
            'is_active' => 1,
            'note' => '生活費用のメイン口座',
            'import_aliases' => json_encode(['住信SBI', 'SBIハイブリッド']),
        ]);

        self::assertSame(
            '2026-01-01',
            Account::query()->where('name', 'Main Bank')->sole()->opening_balance_date->toDateString(),
        );
    }

    public function test_authenticated_user_can_edit_their_own_account(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create([
            'name' => 'Wallet',
            'type' => 'cash',
            'currency' => 'JPY',
            'initial_balance' => 1000,
        ]);

        $this->actingAs($user)
            ->get(route('accounts.edit', $account))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Accounts/Edit')
                ->where('account.id', $account->id)
                ->where('account.name', 'Wallet'));

        $response = $this->actingAs($user)->put(route('accounts.update', $account), [
            'name' => 'Updated Wallet',
            'type' => 'cash',
            'currency' => 'usd',
            'initial_balance' => '2500.75',
            'display_order' => 3,
            'is_active' => false,
            'note' => '更新後のメモ',
            'import_aliases' => "Wallet Main\nWallet Sub",
        ]);

        $response->assertRedirect(route('accounts.index'));

        $this->assertDatabaseHas('accounts', [
            'id' => $account->id,
            'user_id' => $user->id,
            'name' => 'Updated Wallet',
            'currency' => 'USD',
            'display_order' => 3,
            'is_active' => 0,
            'note' => '更新後のメモ',
            'import_aliases' => json_encode(['Wallet Main', 'Wallet Sub']),
        ]);
    }

    public function test_authenticated_user_can_delete_their_own_account(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();

        $response = $this->actingAs($user)
            ->delete(route('accounts.destroy', $account));

        $response->assertRedirect(route('accounts.index'));

        $this->assertModelMissing($account);
    }

    public function test_account_with_related_transactions_or_imports_cannot_be_deleted(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $category = Category::factory()->for($user)->create([
            'type' => 'expense',
        ]);

        Transaction::factory()
            ->forAccount($account)
            ->forCategory($category)
            ->create([
                'user_id' => $user->id,
            ]);

        Import::create([
            'user_id' => $user->id,
            'account_id' => $account->id,
            'source_name' => 'money_forward',
            'original_filename' => 'test.csv',
            'storage_path' => 'imports/test.csv',
            'status' => 'uploaded',
            'total_rows' => 0,
            'imported_rows' => 0,
            'skipped_rows' => 0,
            'duplicate_rows' => 0,
        ]);

        $response = $this->actingAs($user)
            ->delete(route('accounts.destroy', $account));

        $response
            ->assertRedirect(route('accounts.index'))
            ->assertSessionHas('error', '取引または取込履歴に紐づく口座は削除できません。必要な場合は無効化してください。');

        $this->assertDatabaseHas('accounts', [
            'id' => $account->id,
        ]);
    }

    public function test_account_used_only_as_transfer_destination_cannot_be_deleted(): void
    {
        $user = User::factory()->create();
        $sourceAccount = Account::factory()->for($user)->create();
        $destinationAccount = Account::factory()->for($user)->create();

        Transaction::factory()
            ->transfer($sourceAccount, $destinationAccount)
            ->create([
                'user_id' => $user->id,
                'currency' => $sourceAccount->currency,
            ]);

        $response = $this->actingAs($user)
            ->delete(route('accounts.destroy', $destinationAccount));

        $response
            ->assertRedirect(route('accounts.index'))
            ->assertSessionHas('error', '取引または取込履歴に紐づく口座は削除できません。必要な場合は無効化してください。');

        $this->assertDatabaseHas('accounts', [
            'id' => $destinationAccount->id,
        ]);
        $this->assertDatabaseHas('transactions', [
            'account_id' => $sourceAccount->id,
            'transfer_account_id' => $destinationAccount->id,
        ]);
    }

    public function test_account_cannot_be_deleted_after_import_deletion_when_soft_deleted_transactions_still_reference_it(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $category = Category::factory()->for($user)->create([
            'type' => 'expense',
        ]);

        $import = Import::create([
            'user_id' => $user->id,
            'account_id' => $account->id,
            'source_name' => 'money_forward',
            'original_filename' => 'test.csv',
            'storage_path' => 'imports/test.csv',
            'status' => 'imported',
            'total_rows' => 1,
            'imported_rows' => 1,
            'skipped_rows' => 0,
            'duplicate_rows' => 0,
        ]);

        Storage::disk('local')->put('imports/test.csv', 'dummy');

        Transaction::factory()
            ->forAccount($account)
            ->forCategory($category)
            ->create([
                'user_id' => $user->id,
                'import_id' => $import->id,
                'type' => 'expense',
            ]);

        $this->actingAs($user)
            ->delete(route('imports.destroy', $import))
            ->assertRedirect(route('imports.index'));

        $response = $this->actingAs($user)
            ->delete(route('accounts.destroy', $account));

        $response
            ->assertRedirect(route('accounts.index'))
            ->assertSessionHas('error', '取引または取込履歴に紐づく口座は削除できません。必要な場合は無効化してください。');

        $this->assertDatabaseHas('accounts', [
            'id' => $account->id,
        ]);
    }

    public function test_account_referenced_by_validated_import_row_cannot_be_deleted(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();

        $import = Import::create([
            'user_id' => $user->id,
            'account_id' => null,
            'source_name' => 'money_forward',
            'original_filename' => 'test.csv',
            'storage_path' => 'imports/test.csv',
            'status' => 'validated',
            'total_rows' => 1,
            'imported_rows' => 0,
            'skipped_rows' => 0,
            'duplicate_rows' => 0,
        ]);

        ImportRow::create([
            'import_id' => $import->id,
            'row_number' => 2,
            'raw_payload' => ['内容' => '店舗'],
            'transaction_date' => '2026-04-10',
            'amount' => '1000.00',
            'merchant_name' => '店舗',
            'description' => 'Wallet / 未分類 / 未分類',
            'account_name' => $account->name,
            'category_name' => '未分類',
            'subcategory_name' => '未分類',
            'detected_type' => 'expense',
            'duplicate_hash' => null,
            'resolved_account_id' => $account->id,
            'resolved_category_id' => null,
            'resolved_subcategory_id' => null,
            'is_calculation_target' => true,
            'is_duplicate_candidate' => false,
            'validation_errors' => null,
            'status' => 'ready',
        ]);

        $response = $this->actingAs($user)
            ->delete(route('accounts.destroy', $account));

        $response
            ->assertRedirect(route('accounts.index'))
            ->assertSessionHas('error', '取引または取込履歴に紐づく口座は削除できません。必要な場合は無効化してください。');

        $this->assertDatabaseHas('accounts', [
            'id' => $account->id,
        ]);
    }

    public function test_account_referenced_as_transfer_destination_by_validated_import_row_cannot_be_deleted(): void
    {
        $user = User::factory()->create();
        $sourceAccount = Account::factory()->for($user)->create([
            'name' => '住信SBIネット銀行',
            'type' => 'bank',
        ]);
        $destinationAccount = Account::factory()->for($user)->create([
            'name' => '三井住友カード',
            'type' => 'credit_card',
        ]);

        $import = Import::create([
            'user_id' => $user->id,
            'account_id' => null,
            'source_name' => 'money_forward',
            'original_filename' => 'test.csv',
            'storage_path' => 'imports/test.csv',
            'status' => 'validated',
            'total_rows' => 1,
            'imported_rows' => 0,
            'skipped_rows' => 0,
            'duplicate_rows' => 0,
        ]);

        ImportRow::create([
            'import_id' => $import->id,
            'row_number' => 2,
            'raw_payload' => ['内容' => '三井住友カード引落'],
            'transaction_date' => '2026-04-10',
            'amount' => '5000.00',
            'merchant_name' => '三井住友カード引落',
            'description' => null,
            'account_name' => $sourceAccount->name,
            'category_name' => null,
            'subcategory_name' => null,
            'detected_type' => 'transfer',
            'duplicate_hash' => 'dummy-hash',
            'resolved_account_id' => $sourceAccount->id,
            'resolved_transfer_account_id' => $destinationAccount->id,
            'resolved_category_id' => null,
            'resolved_subcategory_id' => null,
            'is_calculation_target' => false,
            'is_duplicate_candidate' => false,
            'validation_errors' => null,
            'status' => 'ready',
        ]);

        $response = $this->actingAs($user)
            ->delete(route('accounts.destroy', $destinationAccount));

        $response
            ->assertRedirect(route('accounts.index'))
            ->assertSessionHas('error', '取引または取込履歴に紐づく口座は削除できません。必要な場合は無効化してください。');

        $this->assertDatabaseHas('accounts', [
            'id' => $destinationAccount->id,
        ]);
    }

    public function test_user_cannot_access_or_modify_another_users_account(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $account = Account::factory()->for($otherUser)->create();

        $this->actingAs($user)
            ->get(route('accounts.edit', $account))
            ->assertForbidden();

        $this->actingAs($user)
            ->put(route('accounts.update', $account), [
                'name' => 'Blocked',
                'type' => 'other',
                'currency' => 'JPY',
                'initial_balance' => '0',
                'display_order' => 0,
                'is_active' => true,
                'note' => '',
            ])
            ->assertForbidden();

        $this->actingAs($user)
            ->delete(route('accounts.destroy', $account))
            ->assertForbidden();
    }
}
