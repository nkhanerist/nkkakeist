<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-04-15 12:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_authenticated_user_can_view_their_dashboard(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard/Index')
                ->where('selected_view', 'month')
                ->where('selected_month', '2026-04')
                ->where('selected_year', '2026')
                ->where('year_view_ready', true));
    }

    public function test_dashboard_monthly_summary_is_aggregated_correctly(): void
    {
        [$user] = $this->seedDashboardData();

        $this->actingAs($user)
            ->get(route('dashboard', ['month' => '2026-04']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('monthly_summaries', 1)
                ->where('monthly_summaries.0.currency', 'JPY')
                ->where('monthly_summaries.0.income_total', '5000.00')
                ->where('monthly_summaries.0.expense_total', '1200.00')
                ->where('monthly_summaries.0.balance_total', '3800.00'));
    }

    public function test_transfer_is_not_included_in_monthly_summary(): void
    {
        [$user] = $this->seedDashboardData();

        $this->actingAs($user)
            ->get(route('dashboard', ['month' => '2026-04']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('monthly_summaries.0.income_total', '5000.00')
                ->where('monthly_summaries.0.expense_total', '1200.00')
                ->where('monthly_summaries.0.balance_total', '3800.00'));
    }

    public function test_account_balance_summaries_are_calculated_correctly(): void
    {
        [$user, $wallet, $bank] = $this->seedDashboardData();

        $this->actingAs($user)
            ->get(route('dashboard', ['month' => '2026-04']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('account_summaries', 2)
                ->where('account_summaries.0.id', $wallet->id)
                ->where('account_summaries.0.current_balance', '200.00')
                ->where('account_summaries.1.id', $bank->id)
                ->where('account_summaries.1.current_balance', '11300.00'));
    }

    public function test_category_expenses_include_only_expense_transactions(): void
    {
        [$user, , , $foodCategory] = $this->seedDashboardData();

        $this->actingAs($user)
            ->get(route('dashboard', ['month' => '2026-04']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('category_expenses', 1)
                ->where('category_expenses.0.id', $foodCategory->id)
                ->where('category_expenses.0.currency', 'JPY')
                ->where('category_expenses.0.total_amount', '1200.00'));
    }

    public function test_other_users_data_is_not_mixed_into_dashboard_aggregates(): void
    {
        [$user] = $this->seedDashboardData();
        $otherUser = User::factory()->create();
        $otherAccount = Account::factory()->for($otherUser)->create([
            'initial_balance' => 99999,
        ]);
        $otherCategory = Category::factory()->for($otherUser)->create([
            'name' => 'Other Expense',
            'type' => 'expense',
        ]);

        Transaction::factory()
            ->forAccount($otherAccount)
            ->forCategory($otherCategory)
            ->create([
                'user_id' => $otherUser->id,
                'transaction_date' => '2026-04-10',
                'type' => 'expense',
                'amount' => '8888.00',
            ]);

        Transaction::factory()
            ->forAccount($otherAccount)
            ->forCategory(Category::factory()->for($otherUser)->create(['type' => 'income']))
            ->create([
                'user_id' => $otherUser->id,
                'transaction_date' => '2026-04-12',
                'type' => 'income',
                'amount' => '7777.00',
            ]);

        $this->actingAs($user)
            ->get(route('dashboard', ['month' => '2026-04']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('monthly_summaries.0.income_total', '5000.00')
                ->where('monthly_summaries.0.expense_total', '1200.00')
                ->has('category_expenses', 1));
    }

    public function test_dashboard_month_query_switches_target_month(): void
    {
        [$user] = $this->seedDashboardData();

        $this->actingAs($user)
            ->get(route('dashboard', ['view' => 'month', 'month' => '2026-03']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('selected_view', 'month')
                ->where('selected_month', '2026-03')
                ->where('selected_year', '2026')
                ->where('monthly_summaries.0.currency', 'JPY')
                ->where('monthly_summaries.0.income_total', '0.00')
                ->where('monthly_summaries.0.expense_total', '300.00')
                ->where('monthly_summaries.0.balance_total', '-300.00'));
    }

    public function test_dashboard_year_view_returns_period_props(): void
    {
        [$user] = $this->seedDashboardData();

        $this->actingAs($user)
            ->get(route('dashboard', ['view' => 'year', 'year' => '2026']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('selected_view', 'year')
                ->where('selected_year', '2026')
                ->where('selected_month', '2026-04')
                ->where('selected_period_label', '2026年')
                ->where('year_view_ready', true)
                ->has('year_options')
                ->has('month_options')
                ->has('yearly_summaries', 1)
                ->has('yearly_category_expenses', 1)
                ->has('yearly_trends', 12));
    }

    public function test_dashboard_year_view_preserves_selected_month_query(): void
    {
        [$user] = $this->seedDashboardData();

        $this->actingAs($user)
            ->get(route('dashboard', [
                'view' => 'year',
                'year' => '2025',
                'month' => '2025-12',
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('selected_view', 'year')
                ->where('selected_year', '2025')
                ->where('selected_month', '2025-12'));
    }

    public function test_dashboard_yearly_summary_is_aggregated_correctly(): void
    {
        [$user] = $this->seedDashboardData();

        $this->actingAs($user)
            ->get(route('dashboard', ['view' => 'year', 'year' => '2026']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('yearly_summaries.0.currency', 'JPY')
                ->where('yearly_summaries.0.income_total', '7000.00')
                ->where('yearly_summaries.0.expense_total', '1500.00')
                ->where('yearly_summaries.0.balance_total', '5500.00'));
    }

    public function test_dashboard_yearly_category_expenses_are_aggregated_correctly(): void
    {
        [$user, , , $foodCategory] = $this->seedDashboardData();

        $this->actingAs($user)
            ->get(route('dashboard', ['view' => 'year', 'year' => '2026']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('yearly_category_expenses', 1)
                ->where('yearly_category_expenses.0.currency', 'JPY')
                ->where('yearly_category_expenses.0.items.0.category_id', $foodCategory->id)
                ->where('yearly_category_expenses.0.items.0.category_name', 'Food')
                ->where('yearly_category_expenses.0.items.0.total_amount', '1500.00'));
    }

    public function test_dashboard_yearly_trend_contains_twelve_months(): void
    {
        [$user] = $this->seedDashboardData();

        $this->actingAs($user)
            ->get(route('dashboard', ['view' => 'year', 'year' => '2026']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('yearly_trends', 12)
                ->where('yearly_trends.0.month', '2026-01')
                ->where('yearly_trends.11.month', '2026-12'));
    }

    public function test_dashboard_yearly_trend_returns_zero_for_months_without_transactions(): void
    {
        [$user] = $this->seedDashboardData();

        $this->actingAs($user)
            ->get(route('dashboard', ['view' => 'year', 'year' => '2026']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('yearly_trends.0.summaries.0.currency', 'JPY')
                ->where('yearly_trends.0.summaries.0.income_total', '0.00')
                ->where('yearly_trends.0.summaries.0.expense_total', '0.00')
                ->where('yearly_trends.0.summaries.0.balance_total', '0.00'));
    }

    public function test_dashboard_yearly_trend_returns_zero_rows_even_when_year_has_no_transactions(): void
    {
        $user = User::factory()->create();
        Account::factory()->for($user)->create([
            'currency' => 'JPY',
            'initial_balance' => 1000,
        ]);

        $this->actingAs($user)
            ->get(route('dashboard', ['view' => 'year', 'year' => '2026']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('yearly_summaries', 1)
                ->where('yearly_summaries.0.currency', 'JPY')
                ->where('yearly_summaries.0.income_total', '0.00')
                ->where('yearly_summaries.0.expense_total', '0.00')
                ->where('yearly_summaries.0.balance_total', '0.00')
                ->has('yearly_trends', 12)
                ->where('yearly_trends.0.summaries.0.currency', 'JPY')
                ->where('yearly_trends.0.summaries.0.income_total', '0.00')
                ->where('yearly_trends.0.summaries.0.expense_total', '0.00')
                ->where('yearly_trends.0.summaries.0.balance_total', '0.00'));
    }

    public function test_dashboard_year_view_keeps_zero_rows_for_account_currencies_without_year_transactions(): void
    {
        $user = User::factory()->create();
        $jpyAccount = Account::factory()->for($user)->create([
            'currency' => 'JPY',
            'initial_balance' => 1000,
        ]);
        Account::factory()->for($user)->create([
            'currency' => 'USD',
            'initial_balance' => 200,
        ]);
        $incomeCategory = Category::factory()->for($user)->create([
            'name' => 'Salary',
            'type' => 'income',
        ]);

        Transaction::factory()
            ->forAccount($jpyAccount)
            ->forCategory($incomeCategory)
            ->create([
                'user_id' => $user->id,
                'transaction_date' => '2026-04-10',
                'type' => 'income',
                'amount' => '5000.00',
                'currency' => 'JPY',
            ]);

        $this->actingAs($user)
            ->get(route('dashboard', ['view' => 'year', 'year' => '2026']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('yearly_summaries', 2)
                ->where('yearly_summaries.0.currency', 'JPY')
                ->where('yearly_summaries.0.income_total', '5000.00')
                ->where('yearly_summaries.1.currency', 'USD')
                ->where('yearly_summaries.1.income_total', '0.00')
                ->where('yearly_trends.3.summaries.0.currency', 'JPY')
                ->where('yearly_trends.3.summaries.1.currency', 'USD')
                ->where('yearly_trends.3.summaries.1.income_total', '0.00')
                ->where('yearly_trends.3.summaries.1.expense_total', '0.00')
                ->where('yearly_trends.3.summaries.1.balance_total', '0.00'));
    }

    public function test_dashboard_year_view_excludes_currencies_from_future_accounts(): void
    {
        $user = User::factory()->create();
        $jpyAccount = Account::factory()->for($user)->create([
            'currency' => 'JPY',
            'initial_balance' => 1000,
            'created_at' => '2026-01-10 00:00:00',
        ]);
        Account::factory()->for($user)->create([
            'currency' => 'USD',
            'initial_balance' => 200,
            'created_at' => '2027-01-10 00:00:00',
        ]);
        $incomeCategory = Category::factory()->for($user)->create([
            'name' => 'Salary',
            'type' => 'income',
        ]);

        Transaction::factory()
            ->forAccount($jpyAccount)
            ->forCategory($incomeCategory)
            ->create([
                'user_id' => $user->id,
                'transaction_date' => '2026-04-10',
                'type' => 'income',
                'amount' => '5000.00',
                'currency' => 'JPY',
            ]);

        $this->actingAs($user)
            ->get(route('dashboard', ['view' => 'year', 'year' => '2026']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('yearly_summaries', 1)
                ->where('yearly_summaries.0.currency', 'JPY')
                ->has('yearly_trends', 12)
                ->has('yearly_trends.3.summaries', 1)
                ->where('yearly_trends.3.summaries.0.currency', 'JPY'));
    }

    public function test_dashboard_year_view_returns_empty_arrays_when_user_has_no_accounts_and_no_transactions(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('dashboard', ['view' => 'year', 'year' => '2026']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('yearly_summaries', 0)
                ->has('yearly_category_expenses', 0)
                ->has('yearly_trends', 12)
                ->where('yearly_trends.0.summaries', []));
    }

    public function test_year_dashboard_preserves_precision_for_fractional_amounts_under_sqlite(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create([
            'currency' => 'USD',
            'initial_balance' => 0,
        ]);
        $incomeCategory = Category::factory()->for($user)->create([
            'name' => 'USD Income',
            'type' => 'income',
        ]);

        Transaction::factory()
            ->forAccount($account)
            ->forCategory($incomeCategory)
            ->create([
                'user_id' => $user->id,
                'transaction_date' => '2026-04-11',
                'type' => 'income',
                'amount' => '10.10',
                'currency' => 'USD',
            ]);

        Transaction::factory()
            ->forAccount($account)
            ->forCategory($incomeCategory)
            ->create([
                'user_id' => $user->id,
                'transaction_date' => '2026-04-12',
                'type' => 'income',
                'amount' => '10.20',
                'currency' => 'USD',
            ]);

        $this->actingAs($user)
            ->get(route('dashboard', ['view' => 'year', 'year' => '2026']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('yearly_summaries.0.currency', 'USD')
                ->where('yearly_summaries.0.income_total', '20.30')
                ->where('yearly_summaries.0.balance_total', '20.30')
                ->where('yearly_trends.3.summaries.0.income_total', '20.30')
                ->where('yearly_trends.3.summaries.0.balance_total', '20.30'));
    }

    public function test_year_view_transfer_is_not_included_in_yearly_summary(): void
    {
        [$user] = $this->seedDashboardData();

        $this->actingAs($user)
            ->get(route('dashboard', ['view' => 'year', 'year' => '2026']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('yearly_summaries.0.income_total', '7000.00')
                ->where('yearly_summaries.0.expense_total', '1500.00')
                ->where('yearly_summaries.0.balance_total', '5500.00'));
    }

    public function test_year_view_income_and_transfer_are_not_included_in_yearly_category_expenses(): void
    {
        [$user] = $this->seedDashboardData();

        $this->actingAs($user)
            ->get(route('dashboard', ['view' => 'year', 'year' => '2026']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('yearly_category_expenses', 1)
                ->has('yearly_category_expenses.0.items', 1)
                ->where('yearly_category_expenses.0.items.0.category_name', 'Food'));
    }

    public function test_other_users_data_is_not_mixed_into_year_dashboard_aggregates(): void
    {
        [$user] = $this->seedDashboardData();
        $otherUser = User::factory()->create();
        $otherAccount = Account::factory()->for($otherUser)->create([
            'initial_balance' => 99999,
        ]);
        $otherExpenseCategory = Category::factory()->for($otherUser)->create([
            'name' => 'Other Expense',
            'type' => 'expense',
        ]);
        $otherIncomeCategory = Category::factory()->for($otherUser)->create([
            'name' => 'Other Income',
            'type' => 'income',
        ]);

        Transaction::factory()
            ->forAccount($otherAccount)
            ->forCategory($otherExpenseCategory)
            ->create([
                'user_id' => $otherUser->id,
                'transaction_date' => '2026-04-10',
                'type' => 'expense',
                'amount' => '8888.00',
            ]);

        Transaction::factory()
            ->forAccount($otherAccount)
            ->forCategory($otherIncomeCategory)
            ->create([
                'user_id' => $otherUser->id,
                'transaction_date' => '2026-04-12',
                'type' => 'income',
                'amount' => '7777.00',
            ]);

        $this->actingAs($user)
            ->get(route('dashboard', ['view' => 'year', 'year' => '2026']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('yearly_summaries.0.income_total', '7000.00')
                ->where('yearly_summaries.0.expense_total', '1500.00')
                ->where('yearly_summaries.0.balance_total', '5500.00')
                ->where('yearly_category_expenses.0.items.0.total_amount', '1500.00'));
    }

    public function test_year_dashboard_excludes_transactions_marked_as_not_calculation_target(): void
    {
        [$user, $wallet] = $this->seedDashboardData();
        $expenseCategory = Category::factory()->for($user)->create([
            'name' => 'Ignored Year Expense',
            'type' => 'expense',
        ]);

        Transaction::factory()
            ->forAccount($wallet)
            ->forCategory($expenseCategory)
            ->create([
                'user_id' => $user->id,
                'transaction_date' => '2026-01-13',
                'type' => 'expense',
                'amount' => '500.00',
                'currency' => 'JPY',
                'is_calculation_target' => false,
            ]);

        $this->actingAs($user)
            ->get(route('dashboard', ['view' => 'year', 'year' => '2026']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('yearly_summaries.0.expense_total', '1500.00')
                ->where('yearly_summaries.0.balance_total', '5500.00')
                ->where('yearly_trends.0.summaries.0.expense_total', '0.00')
                ->where('yearly_category_expenses.0.items.0.total_amount', '1500.00'));
    }

    public function test_year_dashboard_uses_two_fraction_digits_for_non_zero_decimal_currencies(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create([
            'currency' => 'KWD',
            'initial_balance' => 0,
        ]);
        $incomeCategory = Category::factory()->for($user)->create([
            'name' => 'KWD Income',
            'type' => 'income',
        ]);

        Transaction::factory()
            ->forAccount($account)
            ->forCategory($incomeCategory)
            ->create([
                'user_id' => $user->id,
                'transaction_date' => '2026-04-11',
                'type' => 'income',
                'amount' => '1.23',
                'currency' => 'KWD',
            ]);

        $this->actingAs($user)
            ->get(route('dashboard', ['view' => 'year', 'year' => '2026']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('yearly_summaries.0.currency', 'KWD')
                ->where('yearly_summaries.0.income_total', '1.23')
                ->where('yearly_summaries.0.expense_total', '0.00')
                ->where('yearly_summaries.0.balance_total', '1.23')
                ->where('yearly_trends.3.summaries.0.currency', 'KWD')
                ->where('yearly_trends.3.summaries.0.income_total', '1.23')
                ->where('yearly_trends.3.summaries.0.balance_total', '1.23'));
    }

    public function test_yearly_category_expenses_include_uncategorized_expense(): void
    {
        [$user, $wallet] = $this->seedDashboardData();

        Transaction::factory()->forAccount($wallet)->create([
            'user_id' => $user->id,
            'transaction_date' => '2026-04-16',
            'type' => 'expense',
            'amount' => '250.00',
            'currency' => 'JPY',
            'category_id' => null,
            'subcategory_id' => null,
        ]);

        $this->actingAs($user)
            ->get(route('dashboard', ['view' => 'year', 'year' => '2026']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('yearly_category_expenses.0.items.1.category_id', null)
                ->where('yearly_category_expenses.0.items.1.category_name', '未分類')
                ->where('yearly_category_expenses.0.items.1.total_amount', '250.00'));
    }

    public function test_yearly_category_expenses_are_empty_when_year_has_no_expenses(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create([
            'currency' => 'JPY',
            'initial_balance' => 0,
        ]);
        $incomeCategory = Category::factory()->for($user)->create([
            'name' => 'Salary',
            'type' => 'income',
        ]);

        Transaction::factory()
            ->forAccount($account)
            ->forCategory($incomeCategory)
            ->create([
                'user_id' => $user->id,
                'transaction_date' => '2026-04-11',
                'type' => 'income',
                'amount' => '1000.00',
                'currency' => 'JPY',
            ]);

        $this->actingAs($user)
            ->get(route('dashboard', ['view' => 'year', 'year' => '2026']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('yearly_category_expenses', 0));
    }

    public function test_dashboard_past_month_account_summaries_are_serialized_as_a_list(): void
    {
        [$user] = $this->seedDashboardData();

        Account::factory()->for($user)->create([
            'name' => 'Future Account',
            'currency' => 'JPY',
            'display_order' => 99,
            'created_at' => '2026-04-10 00:00:00',
        ]);

        $this->actingAs($user)
            ->get(route('dashboard', ['month' => '2026-03']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('selected_month', '2026-03')
                ->where('account_summaries', function ($summaries) {
                    $items = $summaries->all();

                    return array_is_list($items)
                        && count($items) === 2
                        && $items[0]['name'] === 'Wallet'
                        && $items[1]['name'] === 'Main Bank';
                }));
    }

    public function test_invalid_month_query_falls_back_to_current_month(): void
    {
        [$user] = $this->seedDashboardData();

        $this->actingAs($user)
            ->get(route('dashboard', ['month' => 'invalid']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('selected_month', '2026-04')
                ->where('monthly_summaries.0.income_total', '5000.00'));
    }

    public function test_invalid_view_query_falls_back_to_month(): void
    {
        [$user] = $this->seedDashboardData();

        $this->actingAs($user)
            ->get(route('dashboard', ['view' => 'invalid', 'month' => '2026-04']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('selected_view', 'month')
                ->where('selected_month', '2026-04')
                ->where('monthly_summaries.0.income_total', '5000.00'));
    }

    public function test_invalid_numeric_month_query_falls_back_to_current_month(): void
    {
        [$user] = $this->seedDashboardData();

        $this->actingAs($user)
            ->get(route('dashboard', ['month' => '2026-13']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('selected_month', '2026-04')
                ->where('monthly_summaries.0.income_total', '5000.00'));
    }

    public function test_invalid_year_query_falls_back_to_current_year(): void
    {
        [$user] = $this->seedDashboardData();

        $this->actingAs($user)
            ->get(route('dashboard', ['view' => 'year', 'year' => 'invalid']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('selected_view', 'year')
                ->where('selected_year', '2026')
                ->where('selected_period_label', '2026年'));
    }

    public function test_legacy_month_query_without_view_is_supported(): void
    {
        [$user] = $this->seedDashboardData();

        $this->actingAs($user)
            ->get(route('dashboard', ['month' => '2026-03']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('selected_view', 'month')
                ->where('selected_month', '2026-03')
                ->where('selected_year', '2026')
                ->where('monthly_summaries.0.expense_total', '300.00'));
    }

    public function test_monthly_trend_contains_recent_six_months(): void
    {
        [$user] = $this->seedDashboardData();

        $this->actingAs($user)
            ->get(route('dashboard', ['month' => '2026-04']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('monthly_trends', 6)
                ->where('monthly_trends.4.month', '2026-03')
                ->where('monthly_trends.4.summaries.0.expense_total', '300.00')
                ->where('monthly_trends.5.month', '2026-04')
                ->where('monthly_trends.5.summaries.0.income_total', '5000.00')
                ->where('monthly_trends.5.summaries.0.expense_total', '1200.00'));
    }

    public function test_dashboard_separates_monthly_summaries_by_currency(): void
    {
        [$user, $wallet] = $this->seedDashboardData();
        $usdCategory = Category::factory()->for($user)->create([
            'name' => 'Overseas',
            'type' => 'expense',
        ]);

        Transaction::factory()
            ->forAccount($wallet)
            ->forCategory($usdCategory)
            ->create([
                'user_id' => $user->id,
                'transaction_date' => '2026-04-14',
                'type' => 'expense',
                'amount' => '20.00',
                'currency' => 'USD',
            ]);

        $this->actingAs($user)
            ->get(route('dashboard', ['month' => '2026-04']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('monthly_summaries', 2)
                ->where('monthly_summaries.0.currency', 'JPY')
                ->where('monthly_summaries.0.expense_total', '1200.00')
                ->where('monthly_summaries.1.currency', 'USD')
                ->where('monthly_summaries.1.expense_total', '20.00'));
    }

    public function test_account_balance_summary_excludes_transactions_in_other_currencies(): void
    {
        [$user, $wallet] = $this->seedDashboardData();
        $usdCategory = Category::factory()->for($user)->create([
            'name' => 'USD Expense',
            'type' => 'expense',
        ]);

        Transaction::factory()
            ->forAccount($wallet)
            ->forCategory($usdCategory)
            ->create([
                'user_id' => $user->id,
                'transaction_date' => '2026-04-13',
                'type' => 'expense',
                'amount' => '50.00',
                'currency' => 'USD',
            ]);

        $this->actingAs($user)
            ->get(route('dashboard', ['month' => '2026-04']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('account_summaries.0.id', $wallet->id)
                ->where('account_summaries.0.current_balance', '200.00'));
    }

    public function test_cross_currency_transfer_is_excluded_from_account_balance_without_conversion(): void
    {
        [$user, $wallet] = $this->seedDashboardData();
        $usdAccount = Account::factory()->for($user)->create([
            'name' => 'USD Wallet',
            'currency' => 'USD',
            'initial_balance' => 50,
            'display_order' => 3,
        ]);

        Transaction::factory()
            ->transfer($wallet, $usdAccount)
            ->create([
                'user_id' => $user->id,
                'transaction_date' => '2026-04-13',
                'amount' => '25.00',
                'currency' => 'JPY',
            ]);

        $this->actingAs($user)
            ->get(route('dashboard', ['month' => '2026-04']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('account_summaries.0.id', $wallet->id)
                ->where('account_summaries.0.current_balance', '175.00')
                ->where('account_summaries.2.id', $usdAccount->id)
                ->where('account_summaries.2.current_balance', '50.00'));
    }

    public function test_legacy_transfer_with_currency_matching_neither_account_still_reduces_source_balance(): void
    {
        [$user, $wallet] = $this->seedDashboardData();
        $usdAccount = Account::factory()->for($user)->create([
            'name' => 'USD Wallet',
            'currency' => 'USD',
            'initial_balance' => 50,
            'display_order' => 3,
        ]);

        Transaction::factory()
            ->transfer($wallet, $usdAccount)
            ->create([
                'user_id' => $user->id,
                'transaction_date' => '2026-04-14',
                'amount' => '10.00',
                'currency' => 'EUR',
            ]);

        $this->actingAs($user)
            ->get(route('dashboard', ['month' => '2026-04']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('account_summaries.0.id', $wallet->id)
                ->where('account_summaries.0.current_balance', '190.00')
                ->where('account_summaries.2.id', $usdAccount->id)
                ->where('account_summaries.2.current_balance', '50.00'));
    }

    public function test_legacy_same_currency_transfer_with_mismatched_transaction_currency_affects_both_accounts(): void
    {
        [$user, $wallet] = $this->seedDashboardData();
        $bank = Account::factory()->for($user)->create([
            'name' => 'Legacy Bank',
            'currency' => 'JPY',
            'initial_balance' => 3000,
            'display_order' => 3,
        ]);

        Transaction::factory()
            ->transfer($wallet, $bank)
            ->create([
                'user_id' => $user->id,
                'transaction_date' => '2026-04-14',
                'amount' => '250.00',
                'currency' => 'USD',
            ]);

        $this->actingAs($user)
            ->get(route('dashboard', ['month' => '2026-04']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('account_summaries.0.id', $wallet->id)
                ->where('account_summaries.0.current_balance', '-50.00')
                ->where('account_summaries.2.id', $bank->id)
                ->where('account_summaries.2.current_balance', '3250.00'));
    }

    public function test_dashboard_preserves_precision_for_large_amounts(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create([
            'currency' => 'JPY',
            'initial_balance' => 0,
        ]);
        $expenseCategory = Category::factory()->for($user)->create([
            'name' => 'High Expense',
            'type' => 'expense',
        ]);
        $incomeCategory = Category::factory()->for($user)->create([
            'name' => 'High Income',
            'type' => 'income',
        ]);

        Transaction::factory()
            ->forAccount($account)
            ->forCategory($incomeCategory)
            ->create([
                'user_id' => $user->id,
                'transaction_date' => '2026-04-11',
                'type' => 'income',
                'amount' => '999999999999.99',
                'currency' => 'JPY',
            ]);

        Transaction::factory()
            ->forAccount($account)
            ->forCategory($expenseCategory)
            ->create([
                'user_id' => $user->id,
                'transaction_date' => '2026-04-12',
                'type' => 'expense',
                'amount' => '0.01',
                'currency' => 'JPY',
            ]);

        $this->actingAs($user)
            ->get(route('dashboard', ['month' => '2026-04']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('monthly_summaries.0.income_total', '999999999999.99')
                ->where('monthly_summaries.0.expense_total', '0.01')
                ->where('monthly_summaries.0.balance_total', '999999999999.98')
                ->where('category_expenses.0.total_amount', '0.01')
                ->where('monthly_trends.5.summaries.0.income_total', '999999999999.99')
                ->where('monthly_trends.5.summaries.0.expense_total', '0.01')
                ->where('monthly_trends.5.summaries.0.balance_total', '999999999999.98'));
    }

    public function test_dashboard_excludes_transactions_marked_as_not_calculation_target(): void
    {
        [$user, $wallet] = $this->seedDashboardData();
        $expenseCategory = Category::factory()->for($user)->create([
            'name' => 'Ignored Expense',
            'type' => 'expense',
        ]);

        Transaction::factory()
            ->forAccount($wallet)
            ->forCategory($expenseCategory)
            ->create([
                'user_id' => $user->id,
                'transaction_date' => '2026-04-13',
                'type' => 'expense',
                'amount' => '500.00',
                'currency' => 'JPY',
                'is_calculation_target' => false,
            ]);

        $this->actingAs($user)
            ->get(route('dashboard', ['month' => '2026-04']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('monthly_summaries.0.expense_total', '1200.00')
                ->where('account_summaries.0.current_balance', '200.00')
                ->has('category_expenses', 1));
    }

    public function test_transfer_marked_as_not_calculation_target_still_affects_account_balances(): void
    {
        $user = User::factory()->create();
        $bank = Account::factory()->for($user)->create([
            'name' => 'Bank',
            'type' => 'bank',
            'currency' => 'JPY',
            'initial_balance' => 10000,
            'display_order' => 1,
        ]);
        $card = Account::factory()->for($user)->create([
            'name' => 'Card',
            'type' => 'credit_card',
            'currency' => 'JPY',
            'initial_balance' => 0,
            'display_order' => 2,
        ]);
        $expenseCategory = Category::factory()->for($user)->create([
            'name' => 'Shopping',
            'type' => 'expense',
        ]);

        Transaction::factory()
            ->forAccount($card)
            ->forCategory($expenseCategory)
            ->create([
                'user_id' => $user->id,
                'transaction_date' => '2026-04-05',
                'type' => 'expense',
                'amount' => '3000.00',
                'currency' => 'JPY',
                'is_calculation_target' => true,
            ]);

        Transaction::factory()
            ->transfer($bank, $card)
            ->create([
                'user_id' => $user->id,
                'transaction_date' => '2026-04-27',
                'amount' => '3000.00',
                'currency' => 'JPY',
                'is_calculation_target' => false,
            ]);

        $this->actingAs($user)
            ->get(route('dashboard', ['month' => '2026-04']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('monthly_summaries.0.expense_total', '3000.00')
                ->where('monthly_summaries.0.balance_total', '-3000.00')
                ->where('account_summaries.0.name', 'Bank')
                ->where('account_summaries.0.current_balance', '7000.00')
                ->where('account_summaries.1.name', 'Card')
                ->where('account_summaries.1.current_balance', '0.00'));
    }

    public function test_code_payment_transfer_chain_keeps_only_the_expense_in_summary(): void
    {
        $user = User::factory()->create();
        $card = Account::factory()->for($user)->create([
            'name' => 'Card',
            'type' => 'credit_card',
            'currency' => 'JPY',
            'initial_balance' => 0,
            'display_order' => 1,
        ]);
        $codePayment = Account::factory()->for($user)->create([
            'name' => 'd払い',
            'type' => 'e_money',
            'currency' => 'JPY',
            'initial_balance' => 0,
            'display_order' => 2,
        ]);
        $expenseCategory = Category::factory()->for($user)->create([
            'name' => 'Convenience',
            'type' => 'expense',
        ]);

        Transaction::factory()
            ->forAccount($codePayment)
            ->forCategory($expenseCategory)
            ->create([
                'user_id' => $user->id,
                'transaction_date' => '2026-04-05',
                'type' => 'expense',
                'amount' => '1200.00',
                'currency' => 'JPY',
                'is_calculation_target' => true,
            ]);

        Transaction::factory()
            ->transfer($card, $codePayment)
            ->create([
                'user_id' => $user->id,
                'transaction_date' => '2026-04-20',
                'amount' => '1200.00',
                'currency' => 'JPY',
                'is_calculation_target' => false,
            ]);

        $this->actingAs($user)
            ->get(route('dashboard', ['month' => '2026-04']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('monthly_summaries.0.expense_total', '1200.00')
                ->where('monthly_summaries.0.balance_total', '-1200.00')
                ->where('category_expenses.0.name', 'Convenience')
                ->where('category_expenses.0.total_amount', '1200.00')
                ->where('account_summaries.0.name', 'Card')
                ->where('account_summaries.0.current_balance', '-1200.00')
                ->where('account_summaries.1.name', 'd払い')
                ->where('account_summaries.1.current_balance', '0.00'));
    }

    public function test_account_with_backfilled_past_transactions_is_included_in_past_month_summary(): void
    {
        [$user, $wallet, $bank] = $this->seedDashboardData();

        $futureAccount = Account::factory()->for($user)->create([
            'name' => 'Future Account',
            'currency' => 'JPY',
            'initial_balance' => 9999,
            'display_order' => 3,
            'created_at' => '2026-05-02 10:00:00',
            'updated_at' => '2026-05-02 10:00:00',
        ]);

        $incomeCategory = Category::factory()->for($user)->create([
            'name' => 'Backfilled Income',
            'type' => 'income',
        ]);

        Transaction::factory()
            ->forAccount($futureAccount)
            ->forCategory($incomeCategory)
            ->create([
                'user_id' => $user->id,
                'transaction_date' => '2026-04-20',
                'type' => 'income',
                'amount' => '200.00',
                'currency' => 'JPY',
            ]);

        $this->actingAs($user)
            ->get(route('dashboard', ['month' => '2026-04']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('account_summaries', 3)
                ->where('account_summaries.0.id', $wallet->id)
                ->where('account_summaries.1.id', $bank->id)
                ->where('account_summaries.2.id', $futureAccount->id)
                ->where('account_summaries.2.current_balance', '10199.00'));
    }

    public function test_future_account_without_transactions_is_excluded_from_past_month_summary(): void
    {
        [$user, $wallet, $bank] = $this->seedDashboardData();

        Account::factory()->for($user)->create([
            'name' => 'Future Empty Account',
            'currency' => 'JPY',
            'initial_balance' => 9999,
            'display_order' => 3,
            'created_at' => '2026-05-02 10:00:00',
            'updated_at' => '2026-05-02 10:00:00',
        ]);

        $this->actingAs($user)
            ->get(route('dashboard', ['month' => '2026-04']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('account_summaries', 2)
                ->where('account_summaries.0.id', $wallet->id)
                ->where('account_summaries.1.id', $bank->id));
    }

    public function test_future_account_with_only_cross_currency_incoming_transfer_is_excluded_from_past_month_summary(): void
    {
        [$user, $wallet, $bank] = $this->seedDashboardData();

        $futureUsdAccount = Account::factory()->for($user)->create([
            'name' => 'Future USD Account',
            'currency' => 'USD',
            'initial_balance' => 9999,
            'display_order' => 3,
            'created_at' => '2026-05-02 10:00:00',
            'updated_at' => '2026-05-02 10:00:00',
        ]);

        Transaction::factory()
            ->transfer($wallet, $futureUsdAccount)
            ->create([
                'user_id' => $user->id,
                'transaction_date' => '2026-04-20',
                'type' => 'transfer',
                'amount' => '200.00',
                'currency' => 'JPY',
            ]);

        $this->actingAs($user)
            ->get(route('dashboard', ['month' => '2026-04']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('account_summaries', 2)
                ->where('account_summaries.0.id', $wallet->id)
                ->where('account_summaries.1.id', $bank->id));
    }

    public function test_uncategorized_expense_is_included_in_category_expense_summary(): void
    {
        [$user, $wallet] = $this->seedDashboardData();

        Transaction::factory()
            ->forAccount($wallet)
            ->create([
                'user_id' => $user->id,
                'transaction_date' => '2026-04-09',
                'type' => 'expense',
                'amount' => '400.00',
                'category_id' => null,
                'subcategory_id' => null,
            ]);

        $this->actingAs($user)
            ->get(route('dashboard', ['month' => '2026-04']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('category_expenses', 2)
                ->where('category_expenses.0.name', 'Food')
                ->where('category_expenses.1.name', '未分類')
                ->where('category_expenses.1.total_amount', '400.00'));
    }

    /**
     * @return array{0: User, 1: Account, 2: Account, 3: Category}
     */
    private function seedDashboardData(): array
    {
        $user = User::factory()->create();
        $wallet = Account::factory()->for($user)->create([
            'name' => 'Wallet',
            'type' => 'cash',
            'initial_balance' => 1000,
            'display_order' => 1,
        ]);
        $bank = Account::factory()->for($user)->create([
            'name' => 'Main Bank',
            'type' => 'bank',
            'initial_balance' => 5000,
            'display_order' => 2,
        ]);
        $foodCategory = Category::factory()->for($user)->create([
            'name' => 'Food',
            'type' => 'expense',
        ]);
        $salaryCategory = Category::factory()->for($user)->create([
            'name' => 'Salary',
            'type' => 'income',
        ]);

        Transaction::factory()
            ->forAccount($wallet)
            ->forCategory($foodCategory)
            ->create([
                'user_id' => $user->id,
                'transaction_date' => '2026-03-10',
                'type' => 'expense',
                'amount' => '300.00',
            ]);

        Transaction::factory()
            ->forAccount($bank)
            ->forCategory($salaryCategory)
            ->create([
                'user_id' => $user->id,
                'transaction_date' => '2026-04-05',
                'type' => 'income',
                'amount' => '5000.00',
            ]);

        Transaction::factory()
            ->forAccount($wallet)
            ->forCategory($foodCategory)
            ->create([
                'user_id' => $user->id,
                'transaction_date' => '2026-04-08',
                'type' => 'expense',
                'amount' => '1200.00',
            ]);

        Transaction::factory()
            ->transfer($bank, $wallet)
            ->create([
                'transaction_date' => '2026-04-10',
                'amount' => '700.00',
            ]);

        Transaction::factory()
            ->forAccount($wallet)
            ->forCategory($foodCategory)
            ->create([
                'user_id' => $user->id,
                'transaction_date' => '2026-04-12',
                'type' => 'expense',
                'amount' => '999.00',
            ])
            ->delete();

        Transaction::factory()
            ->forAccount($bank)
            ->forCategory($salaryCategory)
            ->create([
                'user_id' => $user->id,
                'transaction_date' => '2026-02-01',
                'type' => 'income',
                'amount' => '2000.00',
            ]);

        return [$user, $wallet, $bank, $foodCategory];
    }
}
