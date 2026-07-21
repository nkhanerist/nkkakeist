<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Category;
use App\Models\Subcategory;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TransactionCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_view_only_their_own_transactions(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $account = Account::factory()->for($user)->create();
        $category = Category::factory()->for($user)->create(['type' => 'expense']);
        $subcategory = Subcategory::factory()->forCategory($category)->create();
        $ownTransaction = Transaction::factory()
            ->forAccount($account)
            ->forCategory($category, $subcategory)
            ->create([
                'user_id' => $user->id,
                'merchant_name' => 'My Store',
            ]);

        Transaction::factory()->create([
            'user_id' => $otherUser->id,
        ]);

        $this->actingAs($user)
            ->get(route('transactions.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Transactions/Index')
                ->has('transactions.data', 1)
                ->where('transactions.data.0.id', $ownTransaction->id)
                ->where('transactions.data.0.merchant_name', 'My Store'));
    }

    public function test_authenticated_user_can_create_a_transaction(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $category = Category::factory()->for($user)->create(['type' => 'expense']);
        $subcategory = Subcategory::factory()->forCategory($category)->create();

        $response = $this->actingAs($user)->post(route('transactions.store'), [
            'transaction_date' => '2026-04-01',
            'type' => 'expense',
            'account_id' => $account->id,
            'transfer_account_id' => '',
            'amount' => '3200.50',
            'currency' => 'jpy',
            'merchant_name' => 'Supermarket',
            'description' => '食材',
            'category_id' => $category->id,
            'subcategory_id' => $subcategory->id,
            'payment_method_label' => 'カード',
            'is_confirmed' => true,
            'memo' => '週末の買い物',
        ]);

        $transaction = Transaction::first();

        $response->assertRedirect(route('transactions.show', $transaction));

        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'user_id' => $user->id,
            'account_id' => $account->id,
            'category_id' => $category->id,
            'subcategory_id' => $subcategory->id,
            'type' => 'expense',
            'currency' => 'JPY',
            'merchant_name' => 'Supermarket',
            'is_confirmed' => 1,
        ]);
    }

    public function test_authenticated_user_can_edit_their_own_transaction(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $otherAccount = Account::factory()->for($user)->create();
        $category = Category::factory()->for($user)->create(['type' => 'expense']);
        $subcategory = Subcategory::factory()->forCategory($category)->create();

        $transaction = Transaction::factory()
            ->forAccount($account)
            ->forCategory($category, $subcategory)
            ->create([
                'user_id' => $user->id,
                'type' => 'expense',
            ]);

        $this->actingAs($user)
            ->get(route('transactions.edit', $transaction))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Transactions/Edit')
                ->where('transaction.id', $transaction->id));

        $response = $this->actingAs($user)->put(route('transactions.update', $transaction), [
            'transaction_date' => '2026-04-02',
            'type' => 'transfer',
            'account_id' => $account->id,
            'transfer_account_id' => $otherAccount->id,
            'amount' => '10000',
            'currency' => 'JPY',
            'merchant_name' => '',
            'description' => '口座振替',
            'category_id' => '',
            'subcategory_id' => '',
            'payment_method_label' => '',
            'is_confirmed' => false,
            'memo' => 'ATM 入金',
        ]);

        $response->assertRedirect(route('transactions.show', $transaction));

        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'type' => 'transfer',
            'transfer_account_id' => $otherAccount->id,
            'category_id' => null,
            'subcategory_id' => null,
            'is_confirmed' => 0,
        ]);
    }

    public function test_transaction_show_includes_account_types(): void
    {
        $user = User::factory()->create();
        $cardAccount = Account::factory()->for($user)->create([
            'type' => 'credit_card',
        ]);
        $codePaymentAccount = Account::factory()->for($user)->create([
            'type' => 'e_money',
        ]);

        $transaction = Transaction::factory()
            ->transfer($cardAccount, $codePaymentAccount)
            ->create([
                'user_id' => $user->id,
            ]);

        $this->actingAs($user)
            ->get(route('transactions.show', $transaction))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Transactions/Show')
                ->where('transaction.account.type', 'credit_card')
                ->where('transaction.transfer_account.type', 'e_money'));
    }

    public function test_user_can_update_existing_currency_mismatch_expense_without_changing_currency_shape(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create([
            'currency' => 'JPY',
        ]);
        $category = Category::factory()->for($user)->create(['type' => 'expense']);

        $transaction = Transaction::factory()
            ->forAccount($account)
            ->forCategory($category)
            ->create([
                'user_id' => $user->id,
                'type' => 'expense',
                'currency' => 'USD',
                'memo' => 'before',
            ]);

        $this->actingAs($user)->put(route('transactions.update', $transaction), [
            'transaction_date' => '2026-04-02',
            'type' => 'expense',
            'account_id' => $account->id,
            'transfer_account_id' => '',
            'amount' => '1000',
            'currency' => 'USD',
            'merchant_name' => '',
            'description' => '',
            'category_id' => $category->id,
            'subcategory_id' => '',
            'payment_method_label' => '',
            'is_confirmed' => true,
            'memo' => 'after',
        ])->assertRedirect(route('transactions.show', $transaction));

        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'currency' => 'USD',
            'memo' => 'after',
        ]);
    }

    public function test_user_can_update_existing_cross_currency_transfer_without_changing_currency_shape(): void
    {
        $user = User::factory()->create();
        $jpyAccount = Account::factory()->for($user)->create([
            'currency' => 'JPY',
        ]);
        $usdAccount = Account::factory()->for($user)->create([
            'currency' => 'USD',
        ]);

        $transaction = Transaction::factory()
            ->transfer($jpyAccount, $usdAccount)
            ->create([
                'user_id' => $user->id,
                'currency' => 'JPY',
                'description' => 'before',
            ]);

        $this->actingAs($user)->put(route('transactions.update', $transaction), [
            'transaction_date' => '2026-04-02',
            'type' => 'transfer',
            'account_id' => $jpyAccount->id,
            'transfer_account_id' => $usdAccount->id,
            'amount' => '1000',
            'currency' => 'JPY',
            'merchant_name' => '',
            'description' => 'after',
            'category_id' => '',
            'subcategory_id' => '',
            'payment_method_label' => '',
            'is_confirmed' => true,
            'memo' => '',
        ])->assertRedirect(route('transactions.show', $transaction));

        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'description' => 'after',
            'transfer_account_id' => $usdAccount->id,
        ]);
    }

    public function test_update_preserves_is_calculation_target_when_field_is_omitted(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $category = Category::factory()->for($user)->create(['type' => 'expense']);

        $transaction = Transaction::factory()
            ->forAccount($account)
            ->forCategory($category)
            ->create([
                'user_id' => $user->id,
                'type' => 'expense',
                'is_calculation_target' => false,
                'memo' => 'before',
            ]);

        $payload = [
            'transaction_date' => '2026-04-02',
            'type' => 'expense',
            'account_id' => $account->id,
            'transfer_account_id' => '',
            'amount' => '1000',
            'currency' => 'JPY',
            'merchant_name' => '',
            'description' => '',
            'category_id' => $category->id,
            'subcategory_id' => '',
            'payment_method_label' => '',
            'is_confirmed' => true,
            'memo' => 'after',
        ];

        $this->actingAs($user)
            ->put(route('transactions.update', $transaction), $payload)
            ->assertRedirect(route('transactions.show', $transaction));

        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'memo' => 'after',
            'is_calculation_target' => 0,
        ]);
    }

    public function test_authenticated_user_can_delete_their_own_transaction(): void
    {
        $user = User::factory()->create();
        $transaction = Transaction::factory()->create([
            'user_id' => $user->id,
        ]);

        $response = $this->actingAs($user)
            ->delete(route('transactions.destroy', $transaction));

        $response->assertRedirect(route('transactions.index'));

        $this->assertSoftDeleted($transaction);
    }

    public function test_user_cannot_access_or_modify_another_users_transaction(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $transaction = Transaction::factory()->create([
            'user_id' => $otherUser->id,
        ]);

        $this->actingAs($user)
            ->get(route('transactions.show', $transaction))
            ->assertForbidden();

        $this->actingAs($user)
            ->get(route('transactions.edit', $transaction))
            ->assertForbidden();

        $this->actingAs($user)
            ->put(route('transactions.update', $transaction), [
                'transaction_date' => '2026-04-01',
                'type' => 'expense',
                'account_id' => Account::factory()->for($user)->create()->id,
                'transfer_account_id' => '',
                'amount' => '500',
                'currency' => 'JPY',
                'merchant_name' => '',
                'description' => '',
                'category_id' => Category::factory()->for($user)->create(['type' => 'expense'])->id,
                'subcategory_id' => '',
                'payment_method_label' => '',
                'is_confirmed' => true,
                'memo' => '',
            ])
            ->assertForbidden();

        $this->actingAs($user)
            ->delete(route('transactions.destroy', $transaction))
            ->assertForbidden();
    }

    public function test_user_can_create_transaction_with_their_own_related_resources(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $category = Category::factory()->for($user)->create(['type' => 'income']);
        $subcategory = Subcategory::factory()->forCategory($category)->create();

        $this->actingAs($user)->post(route('transactions.store'), [
            'transaction_date' => '2026-04-03',
            'type' => 'income',
            'account_id' => $account->id,
            'amount' => '50000',
            'currency' => 'JPY',
            'merchant_name' => 'Employer',
            'description' => '入金',
            'category_id' => $category->id,
            'subcategory_id' => $subcategory->id,
            'payment_method_label' => '振込',
            'is_confirmed' => true,
            'memo' => '',
        ])->assertRedirect();

        $this->assertDatabaseHas('transactions', [
            'user_id' => $user->id,
            'account_id' => $account->id,
            'category_id' => $category->id,
            'subcategory_id' => $subcategory->id,
            'type' => 'income',
        ]);
    }

    public function test_user_cannot_create_transaction_with_another_users_related_resources(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $otherAccount = Account::factory()->for($otherUser)->create();
        $otherCategory = Category::factory()->for($otherUser)->create(['type' => 'expense']);
        $otherSubcategory = Subcategory::factory()->forCategory($otherCategory)->create();
        $ownAccount = Account::factory()->for($user)->create();

        $response = $this->actingAs($user)->post(route('transactions.store'), [
            'transaction_date' => '2026-04-03',
            'type' => 'expense',
            'account_id' => $otherAccount->id,
            'amount' => '1000',
            'currency' => 'JPY',
            'merchant_name' => '',
            'description' => '',
            'category_id' => $otherCategory->id,
            'subcategory_id' => $otherSubcategory->id,
            'payment_method_label' => '',
            'is_confirmed' => true,
            'memo' => '',
            'transfer_account_id' => $ownAccount->id,
        ]);

        $response->assertSessionHasErrors(['account_id', 'category_id', 'subcategory_id']);
        $this->assertDatabaseCount('transactions', 0);
    }

    public function test_transfer_requires_transfer_account_rejects_same_account_and_cross_currency_accounts(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $usdAccount = Account::factory()->for($user)->create([
            'currency' => 'USD',
        ]);

        $this->actingAs($user)->post(route('transactions.store'), [
            'transaction_date' => '2026-04-03',
            'type' => 'transfer',
            'account_id' => $account->id,
            'amount' => '1000',
            'currency' => 'JPY',
            'is_confirmed' => true,
        ])->assertSessionHasErrors(['transfer_account_id']);

        $this->actingAs($user)->post(route('transactions.store'), [
            'transaction_date' => '2026-04-03',
            'type' => 'transfer',
            'account_id' => $account->id,
            'transfer_account_id' => $account->id,
            'amount' => '1000',
            'currency' => 'JPY',
            'is_confirmed' => true,
        ])->assertSessionHasErrors(['transfer_account_id']);

        $this->actingAs($user)->post(route('transactions.store'), [
            'transaction_date' => '2026-04-03',
            'type' => 'transfer',
            'account_id' => $account->id,
            'transfer_account_id' => $usdAccount->id,
            'amount' => '1000',
            'currency' => 'JPY',
            'is_confirmed' => true,
        ])->assertSessionHasErrors(['transfer_account_id']);
    }

    public function test_income_and_expense_reject_currency_mismatch_with_account(): void
    {
        $user = User::factory()->create();
        $jpyAccount = Account::factory()->for($user)->create([
            'currency' => 'JPY',
        ]);
        $expenseCategory = Category::factory()->for($user)->create([
            'type' => 'expense',
        ]);
        $incomeCategory = Category::factory()->for($user)->create([
            'type' => 'income',
        ]);

        $this->actingAs($user)->post(route('transactions.store'), [
            'transaction_date' => '2026-04-03',
            'type' => 'expense',
            'account_id' => $jpyAccount->id,
            'amount' => '1000',
            'currency' => 'USD',
            'category_id' => $expenseCategory->id,
            'is_confirmed' => true,
        ])->assertSessionHasErrors(['currency']);

        $this->actingAs($user)->post(route('transactions.store'), [
            'transaction_date' => '2026-04-03',
            'type' => 'income',
            'account_id' => $jpyAccount->id,
            'amount' => '1000',
            'currency' => 'USD',
            'category_id' => $incomeCategory->id,
            'is_confirmed' => true,
        ])->assertSessionHasErrors(['currency']);
    }

    public function test_subcategory_must_belong_to_selected_category(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $category = Category::factory()->for($user)->create(['type' => 'expense']);
        $otherCategory = Category::factory()->for($user)->create(['type' => 'expense']);
        $subcategory = Subcategory::factory()->forCategory($otherCategory)->create();

        $this->actingAs($user)->post(route('transactions.store'), [
            'transaction_date' => '2026-04-03',
            'type' => 'expense',
            'account_id' => $account->id,
            'amount' => '1200',
            'currency' => 'JPY',
            'category_id' => $category->id,
            'subcategory_id' => $subcategory->id,
            'is_confirmed' => true,
        ])->assertSessionHasErrors(['subcategory_id']);
    }

    public function test_transactions_index_filters_work(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $otherAccount = Account::factory()->for($user)->create();
        $food = Category::factory()->for($user)->create([
            'name' => 'Food',
            'type' => 'expense',
        ]);
        $salary = Category::factory()->for($user)->create([
            'name' => 'Salary',
            'type' => 'income',
        ]);

        $matching = Transaction::factory()
            ->forAccount($account)
            ->forCategory($food)
            ->create([
                'user_id' => $user->id,
                'transaction_date' => '2026-04-10',
                'type' => 'expense',
                'merchant_name' => 'Target Shop',
                'is_confirmed' => true,
            ]);

        Transaction::factory()
            ->forAccount($otherAccount)
            ->forCategory($salary)
            ->create([
                'user_id' => $user->id,
                'transaction_date' => '2026-03-01',
                'type' => 'income',
                'merchant_name' => 'Other Shop',
                'is_confirmed' => false,
            ]);

        $this->actingAs($user)
            ->get(route('transactions.index', [
                'date_from' => '2026-04-01',
                'date_to' => '2026-04-30',
                'account_id' => $account->id,
                'category_id' => $food->id,
                'type' => 'expense',
                'keyword' => 'Target',
                'is_confirmed' => '1',
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Transactions/Index')
                ->has('transactions.data', 1)
                ->where('transactions.data.0.id', $matching->id)
                ->where('filters.keyword', 'Target')
                ->where('filters.is_confirmed', '1')
                ->where('filters.calculation_target', 'all'));
    }

    public function test_transactions_index_filters_by_calculation_target(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $category = Category::factory()->for($user)->create([
            'type' => 'expense',
        ]);

        $included = Transaction::factory()
            ->forAccount($account)
            ->forCategory($category)
            ->create([
                'user_id' => $user->id,
                'is_calculation_target' => true,
                'type' => 'expense',
            ]);

        $excluded = Transaction::factory()
            ->forAccount($account)
            ->forCategory($category)
            ->create([
                'user_id' => $user->id,
                'is_calculation_target' => false,
                'type' => 'transfer',
            ]);

        $this->actingAs($user)
            ->get(route('transactions.index', [
                'calculation_target' => 'included',
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('transactions.data', 1)
                ->where('transactions.data.0.id', $included->id)
                ->where('filters.calculation_target', 'included'));

        $this->actingAs($user)
            ->get(route('transactions.index', [
                'calculation_target' => 'excluded',
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('transactions.data', 1)
                ->where('transactions.data.0.id', $excluded->id)
                ->where('filters.calculation_target', 'excluded'));

        $this->actingAs($user)
            ->get(route('transactions.index', [
                'calculation_target' => 'invalid',
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('transactions.data', 2)
                ->where('filters.calculation_target', 'all'));
    }

    public function test_transactions_index_account_filter_includes_transfer_destination(): void
    {
        $user = User::factory()->create();
        $targetAccount = Account::factory()->for($user)->create();
        $sourceAccount = Account::factory()->for($user)->create();
        $unrelatedAccount = Account::factory()->for($user)->create();

        $directTransaction = Transaction::factory()
            ->forAccount($targetAccount)
            ->create([
                'user_id' => $user->id,
                'transaction_date' => '2026-04-01',
            ]);
        $incomingTransfer = Transaction::factory()
            ->transfer($sourceAccount, $targetAccount)
            ->create(['transaction_date' => '2026-04-02']);
        Transaction::factory()
            ->forAccount($unrelatedAccount)
            ->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->get(route('transactions.index', [
                'account_id' => $targetAccount->id,
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('transactions.data', 2)
                ->where('transactions.data.0.id', $incomingTransfer->id)
                ->where('transactions.data.1.id', $directTransaction->id)
                ->where('filters.account_id', (string) $targetAccount->id));
    }

    public function test_transactions_index_filters_by_currency_and_category_state(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create(['currency' => 'JPY']);
        $usdAccount = Account::factory()->for($user)->create(['currency' => 'USD']);
        $category = Category::factory()->for($user)->create(['type' => 'expense']);

        $uncategorizedJpy = Transaction::factory()
            ->forAccount($account)
            ->create([
                'user_id' => $user->id,
                'category_id' => null,
                'currency' => 'JPY',
            ]);
        Transaction::factory()
            ->forAccount($account)
            ->forCategory($category)
            ->create([
                'user_id' => $user->id,
                'currency' => 'JPY',
            ]);
        Transaction::factory()
            ->forAccount($usdAccount)
            ->create([
                'user_id' => $user->id,
                'category_id' => null,
                'currency' => 'USD',
            ]);

        $this->actingAs($user)
            ->get(route('transactions.index', [
                'currency' => 'JPY',
                'category_state' => 'uncategorized',
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('transactions.data', 1)
                ->where('transactions.data.0.id', $uncategorizedJpy->id)
                ->where('filters.currency', 'JPY')
                ->where('filters.category_state', 'uncategorized')
                ->where('currencyOptions', ['JPY', 'USD']));

        $this->actingAs($user)
            ->get(route('transactions.index', [
                'category_state' => 'invalid',
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('transactions.data', 3)
                ->where('filters.category_state', 'all'));
    }

    public function test_transactions_index_combines_calculation_target_with_other_filters(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $otherAccount = Account::factory()->for($user)->create();
        $expenseCategory = Category::factory()->for($user)->create([
            'type' => 'expense',
        ]);

        $matching = Transaction::factory()
            ->forAccount($account)
            ->forCategory($expenseCategory)
            ->create([
                'user_id' => $user->id,
                'type' => 'transfer',
                'is_calculation_target' => false,
            ]);

        Transaction::factory()
            ->forAccount($account)
            ->forCategory($expenseCategory)
            ->create([
                'user_id' => $user->id,
                'type' => 'transfer',
                'is_calculation_target' => true,
            ]);

        Transaction::factory()
            ->forAccount($otherAccount)
            ->forCategory($expenseCategory)
            ->create([
                'user_id' => $user->id,
                'type' => 'transfer',
                'is_calculation_target' => false,
            ]);

        $this->actingAs($user)
            ->get(route('transactions.index', [
                'account_id' => $account->id,
                'type' => 'transfer',
                'calculation_target' => 'excluded',
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('transactions.data', 1)
                ->where('transactions.data.0.id', $matching->id)
                ->where('filters.account_id', (string) $account->id)
                ->where('filters.type', 'transfer')
                ->where('filters.calculation_target', 'excluded'));
    }

    public function test_transactions_index_sorts_with_an_allowlisted_stable_order(): void
    {
        $user = User::factory()->create();
        $zebraAccount = Account::factory()->for($user)->create(['name' => 'Zebra Account']);
        $alphaAccount = Account::factory()->for($user)->create(['name' => 'Alpha Account']);
        $travelCategory = Category::factory()->for($user)->create([
            'name' => 'Travel',
            'type' => 'expense',
        ]);
        $foodCategory = Category::factory()->for($user)->create([
            'name' => 'Food',
            'type' => 'expense',
        ]);

        $zulu = Transaction::factory()
            ->forAccount($zebraAccount)
            ->forCategory($travelCategory)
            ->create([
                'user_id' => $user->id,
                'transaction_date' => '2026-04-01',
                'amount' => '300.00',
                'merchant_name' => 'Zulu Store',
            ]);
        $alpha = Transaction::factory()
            ->forAccount($alphaAccount)
            ->forCategory($foodCategory)
            ->create([
                'user_id' => $user->id,
                'transaction_date' => '2026-04-03',
                'amount' => '100.00',
                'merchant_name' => 'Alpha Store',
            ]);
        $bravo = Transaction::factory()
            ->forAccount($zebraAccount)
            ->create([
                'user_id' => $user->id,
                'category_id' => null,
                'transaction_date' => '2026-04-02',
                'amount' => '200.00',
                'merchant_name' => 'Bravo Store',
            ]);

        $this->actingAs($user)
            ->get(route('transactions.index', [
                'sort' => 'amount',
                'direction' => 'asc',
                'filter_panel' => 'collapsed',
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('transactions.data.0.id', $alpha->id)
                ->where('transactions.data.1.id', $bravo->id)
                ->where('transactions.data.2.id', $zulu->id)
                ->where('filters.sort', 'amount')
                ->where('filters.direction', 'asc')
                ->where('filters.filter_panel', 'collapsed'));

        $this->actingAs($user)
            ->get(route('transactions.index', [
                'sort' => 'account',
                'direction' => 'asc',
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('transactions.data.0.id', $alpha->id)
                ->where('transactions.data.1.id', $bravo->id)
                ->where('transactions.data.2.id', $zulu->id));

        $this->actingAs($user)
            ->get(route('transactions.index', [
                'sort' => 'category',
                'direction' => 'asc',
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('transactions.data.0.id', $alpha->id)
                ->where('transactions.data.1.id', $zulu->id)
                ->where('transactions.data.2.id', $bravo->id));

        $this->actingAs($user)
            ->get(route('transactions.index', [
                'sort' => 'summary',
                'direction' => 'desc',
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('transactions.data.0.id', $zulu->id)
                ->where('transactions.data.1.id', $bravo->id)
                ->where('transactions.data.2.id', $alpha->id));

        $this->actingAs($user)
            ->get(route('transactions.index', [
                'sort' => 'not-a-column',
                'direction' => 'sideways',
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('transactions.data.0.id', $alpha->id)
                ->where('transactions.data.1.id', $bravo->id)
                ->where('transactions.data.2.id', $zulu->id)
                ->where('filters.sort', 'date')
                ->where('filters.direction', 'desc')
                ->where('filters.filter_panel', 'expanded'));
    }
}
