<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountSnapshot;
use App\Models\AssetHistorySnapshot;
use App\Models\Category;
use App\Models\Import;
use App\Models\InvestmentPositionSnapshot;
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

    public function test_dashboard_returns_daily_asset_liability_and_net_worth_trends(): void
    {
        $user = User::factory()->create();
        $bank = Account::factory()->for($user)->create([
            'name' => 'Bank',
            'type' => 'bank',
            'balance_role' => 'asset',
            'balance_method' => 'ledger',
            'include_in_net_worth' => true,
            'initial_balance' => '1000.00',
            'opening_balance_date' => '2026-04-01',
            'currency' => 'JPY',
        ]);
        $securities = Account::factory()->for($user)->create([
            'name' => 'THEO',
            'type' => 'securities',
            'balance_role' => 'asset',
            'balance_method' => 'snapshot',
            'include_in_net_worth' => true,
            'initial_balance' => '0.00',
            'opening_balance_date' => '2026-04-01',
            'currency' => 'JPY',
        ]);
        $card = Account::factory()->for($user)->create([
            'name' => 'Card',
            'type' => 'credit_card',
            'balance_role' => 'liability',
            'balance_method' => 'ledger',
            'include_in_net_worth' => true,
            'initial_balance' => '-200.00',
            'opening_balance_date' => '2026-04-01',
            'currency' => 'JPY',
        ]);
        Account::factory()->for($user)->create([
            'balance_role' => 'clearing',
            'include_in_net_worth' => false,
            'initial_balance' => '-9000.00',
            'opening_balance_date' => '2026-04-01',
        ]);
        Account::factory()->for(User::factory()->create())->create([
            'initial_balance' => '999999.00',
            'opening_balance_date' => '2026-04-01',
        ]);

        AccountSnapshot::query()->create([
            'user_id' => $user->id,
            'account_id' => $securities->id,
            'captured_at' => '2026-04-10 23:59:59',
            'purpose' => 'valuation',
            'balance' => '5000.00',
            'source_name' => 'Test',
        ]);
        Transaction::factory()->forAccount($bank)->create([
            'user_id' => $user->id,
            'transaction_date' => '2026-04-12',
            'type' => 'expense',
            'amount' => '100.00',
            'currency' => 'JPY',
        ]);
        Transaction::factory()->forAccount($card)->create([
            'user_id' => $user->id,
            'transaction_date' => '2026-04-12',
            'type' => 'expense',
            'amount' => '50.00',
            'currency' => 'JPY',
        ]);
        AccountSnapshot::query()->create([
            'user_id' => $user->id,
            'account_id' => $securities->id,
            'captured_at' => '2026-04-15 23:59:59',
            'purpose' => 'valuation',
            'balance' => '5500.00',
            'source_name' => 'Test',
        ]);
        AccountSnapshot::query()->create([
            'user_id' => $user->id,
            'account_id' => $bank->id,
            'captured_at' => '2026-04-15 23:59:59',
            'purpose' => 'official_balance',
            'balance' => '1500.00',
            'source_name' => 'Test',
        ]);
        AccountSnapshot::query()->create([
            'user_id' => $user->id,
            'account_id' => $card->id,
            'captured_at' => '2026-04-15 23:59:59',
            'purpose' => 'official_balance',
            'balance' => '-400.00',
            'source_name' => 'Test',
        ]);

        $this->actingAs($user)
            ->get(route('dashboard', ['month' => '2026-04']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('net_worth_trends', 1)
                ->where('net_worth_trends.0.currency', 'JPY')
                ->has('net_worth_trends.0.points', 2)
                ->where('net_worth_trends.0.points.0.date', '2026-04-10')
                ->where('net_worth_trends.0.points.0.assets', '6000.00')
                ->where('net_worth_trends.0.points.0.liabilities', '200.00')
                ->where('net_worth_trends.0.points.0.net_worth', '5800.00')
                ->where('net_worth_trends.0.points.1.date', '2026-04-15')
                ->where('net_worth_trends.0.points.1.assets', '7000.00')
                ->where('net_worth_trends.0.points.1.liabilities', '400.00')
                ->where('net_worth_trends.0.points.1.net_worth', '6600.00')
                ->has('monthly_report.net_worth_changes', 1)
                ->where('monthly_report.net_worth_changes.0.currency', 'JPY')
                ->where('monthly_report.net_worth_changes.0.start_date', '2026-04-10')
                ->where('monthly_report.net_worth_changes.0.end_date', '2026-04-15')
                ->where('monthly_report.net_worth_changes.0.change_amount', '800.00'));
    }

    public function test_dashboard_returns_all_money_forward_asset_history_for_the_user(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        foreach ([
            ['2017-08-31', '1000000.00', ['預金・現金' => '600000.00', '投資信託' => '400000.00']],
            ['2026-07-18', '22667260.00', ['預金・現金' => '5300726.00', '投資信託' => '15525499.00']],
        ] as [$date, $total, $breakdown]) {
            AssetHistorySnapshot::query()->create([
                'user_id' => $user->id,
                'captured_on' => $date,
                'total_assets' => $total,
                'currency' => 'JPY',
                'source_name' => 'money_forward',
                'duplicate_hash' => hash('sha256', $user->id.$date),
                'breakdown' => $breakdown,
            ]);
        }

        AssetHistorySnapshot::query()->create([
            'user_id' => $otherUser->id,
            'captured_on' => '2026-07-18',
            'total_assets' => '99999999.00',
            'currency' => 'JPY',
            'source_name' => 'money_forward',
            'duplicate_hash' => hash('sha256', 'other'),
            'breakdown' => [],
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('asset_history_trends', 1)
                ->has('asset_history_trends.0.points', 2)
                ->where('asset_history_trends.0.points.0.date', '2017-08-31')
                ->where('asset_history_trends.0.points.1.total_assets', '22667260.00'));
    }

    public function test_dashboard_reports_complete_daily_money_forward_snapshot_status(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create([
            'type' => 'bank',
            'include_in_net_worth' => true,
            'is_active' => true,
        ]);
        $import = Import::query()->create([
            'user_id' => $user->id,
            'source_name' => 'balance_snapshot',
            'source_metadata' => ['source' => 'money_forward'],
            'original_filename' => 'money-forward-balances.json',
            'storage_path' => 'imports/money-forward-balances.json',
            'status' => 'imported',
        ]);
        $snapshot = AccountSnapshot::query()->create([
            'user_id' => $user->id,
            'account_id' => $account->id,
            'import_id' => $import->id,
            'captured_at' => '2026-04-15 23:59:59',
            'purpose' => 'valuation',
            'balance' => '1000.00',
            'source_name' => 'Money Forward',
        ]);
        AccountSnapshot::query()->create([
            'user_id' => $user->id,
            'account_id' => $account->id,
            'import_id' => $import->id,
            'captured_at' => '2026-04-14 23:59:59',
            'purpose' => 'valuation',
            'balance' => '990.00',
            'source_name' => 'Money Forward',
        ]);
        InvestmentPositionSnapshot::query()->create([
            'user_id' => $user->id,
            'account_id' => $account->id,
            'account_snapshot_id' => $snapshot->id,
            'import_id' => $import->id,
            'captured_at' => '2026-04-15 23:59:59',
            'position_key' => hash('sha256', 'instrument'),
            'instrument_name' => 'テスト銘柄',
            'valuation' => '1000.00',
            'currency' => 'JPY',
            'source_name' => 'Money Forward',
        ]);
        AssetHistorySnapshot::query()->create([
            'user_id' => $user->id,
            'import_id' => $import->id,
            'captured_on' => '2026-04-15',
            'total_assets' => '1000.00',
            'currency' => 'JPY',
            'source_name' => 'money_forward',
            'duplicate_hash' => hash('sha256', 'asset-history'),
            'breakdown' => [],
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('daily_snapshot_status.date', '2026-04-15')
                ->where('daily_snapshot_status.state', 'complete')
                ->where('daily_snapshot_status.account_count', 1)
                ->where('daily_snapshot_status.position_count', 1)
                ->where('daily_snapshot_status.asset_history_recorded', true)
                ->where('daily_snapshot_status.required_account_count', 1)
                ->where('daily_snapshot_status.updated_account_count', 1)
                ->where('daily_snapshot_status.coverage_started_on', '2026-04-14')
                ->where('daily_snapshot_status.accounts.0.id', $account->id)
                ->where('daily_snapshot_status.accounts.0.state', 'updated')
                ->has('daily_snapshot_status.coverage_days', 7)
                ->where('daily_snapshot_status.coverage_days.4.state', 'not_required')
                ->where('daily_snapshot_status.coverage_days.4.required_account_count', 0)
                ->where('daily_snapshot_status.coverage_days.5.date', '2026-04-14')
                ->where('daily_snapshot_status.coverage_days.5.state', 'partial')
                ->where('daily_snapshot_status.coverage_days.5.updated_account_count', 1)
                ->where('daily_snapshot_status.coverage_days.5.asset_history_recorded', false)
                ->where('daily_snapshot_status.coverage_days.6.date', '2026-04-15')
                ->where('daily_snapshot_status.coverage_days.6.updated_account_count', 1)
                ->where('daily_snapshot_status.coverage_days.6.required_account_count', 1)
                ->where('daily_snapshot_status.coverage_days.6.asset_history_recorded', true)
                ->where('daily_snapshot_status.coverage_days.6.state', 'complete')
                ->where('daily_snapshot_status.coverage_days.6.position_count', 1)
                ->where('daily_snapshot_status.recent_failures', [])
                ->where('daily_snapshot_status.last_imported_at', fn ($value): bool => is_string($value)));
    }

    public function test_dashboard_reports_missing_daily_snapshot_without_mixing_other_users_data(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $otherAccount = Account::factory()->for($otherUser)->create();

        AccountSnapshot::query()->create([
            'user_id' => $otherUser->id,
            'account_id' => $otherAccount->id,
            'captured_at' => '2026-04-15 23:59:59',
            'purpose' => 'official_balance',
            'balance' => '999999.00',
            'source_name' => 'Money Forward',
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('daily_snapshot_status.state', 'missing')
                ->where('daily_snapshot_status.account_count', 0)
                ->where('daily_snapshot_status.position_count', 0)
                ->where('daily_snapshot_status.asset_history_recorded', false)
                ->where('daily_snapshot_status.required_account_count', 0)
                ->where('daily_snapshot_status.updated_account_count', 0)
                ->where('daily_snapshot_status.coverage_started_on', null)
                ->where('daily_snapshot_status.accounts', [])
                ->has('daily_snapshot_status.coverage_days', 7)
                ->where('daily_snapshot_status.coverage_days.6.state', 'not_required')
                ->where('daily_snapshot_status.coverage_days.6.updated_account_count', 0)
                ->where('daily_snapshot_status.coverage_days.6.position_count', 0)
                ->where('daily_snapshot_status.recent_failures', [])
                ->where('daily_snapshot_status.last_imported_at', null));
    }

    public function test_daily_coverage_does_not_require_an_account_before_its_first_snapshot(): void
    {
        $user = User::factory()->create();
        $existingAccount = Account::factory()->for($user)->create([
            'type' => 'bank',
            'include_in_net_worth' => true,
            'is_active' => true,
        ]);
        $newAccount = Account::factory()->for($user)->create([
            'type' => 'securities',
            'include_in_net_worth' => true,
            'is_active' => true,
        ]);

        foreach (['2026-04-14', '2026-04-15'] as $date) {
            AccountSnapshot::query()->create([
                'user_id' => $user->id,
                'account_id' => $existingAccount->id,
                'captured_at' => $date.' 23:59:59',
                'purpose' => 'official_balance',
                'balance' => '1000.00',
                'source_name' => 'Money Forward',
            ]);
            AssetHistorySnapshot::query()->create([
                'user_id' => $user->id,
                'captured_on' => $date,
                'total_assets' => '1000.00',
                'currency' => 'JPY',
                'source_name' => 'money_forward',
                'duplicate_hash' => hash('sha256', 'asset-history-'.$date),
                'breakdown' => [],
            ]);
        }

        AccountSnapshot::query()->create([
            'user_id' => $user->id,
            'account_id' => $newAccount->id,
            'captured_at' => '2026-04-15 23:59:59',
            'purpose' => 'valuation',
            'balance' => '500.00',
            'source_name' => 'Money Forward',
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('daily_snapshot_status.coverage_started_on', '2026-04-14')
                ->where('daily_snapshot_status.coverage_days.4.state', 'not_required')
                ->where('daily_snapshot_status.coverage_days.5.state', 'complete')
                ->where('daily_snapshot_status.coverage_days.5.required_account_count', 1)
                ->where('daily_snapshot_status.coverage_days.5.updated_account_count', 1)
                ->where('daily_snapshot_status.coverage_days.6.state', 'complete')
                ->where('daily_snapshot_status.coverage_days.6.required_account_count', 2)
                ->where('daily_snapshot_status.coverage_days.6.updated_account_count', 2));
    }

    public function test_dashboard_reports_account_freshness_and_only_unresolved_recent_import_failures(): void
    {
        $user = User::factory()->create();
        $staleBank = Account::factory()->for($user)->create([
            'name' => 'Stale Bank',
            'type' => 'bank',
            'include_in_net_worth' => true,
            'is_active' => true,
            'display_order' => 1,
        ]);
        $neverCapturedCard = Account::factory()->for($user)->create([
            'name' => 'Never Captured Card',
            'type' => 'credit_card',
            'include_in_net_worth' => true,
            'is_active' => true,
            'display_order' => 2,
        ]);
        Account::factory()->for($user)->create([
            'type' => 'point',
            'include_in_net_worth' => true,
            'is_active' => true,
        ]);
        Account::factory()->for($user)->create([
            'type' => 'securities',
            'include_in_net_worth' => false,
            'is_active' => true,
        ]);

        AccountSnapshot::query()->create([
            'user_id' => $user->id,
            'account_id' => $staleBank->id,
            'captured_at' => '2026-04-14 23:59:59',
            'purpose' => 'official_balance',
            'balance' => '1000.00',
            'source_name' => 'Money Forward',
        ]);

        Import::query()->create([
            'user_id' => $user->id,
            'source_name' => 'balance_snapshot',
            'original_filename' => 'failed.json',
            'storage_path' => 'imports/failed.json',
            'status' => 'failed',
            'error_message' => '形式を確認してください。',
            'created_at' => '2026-04-15 09:00:00',
        ]);
        Import::query()->create([
            'user_id' => $user->id,
            'source_name' => 'mobile_suica',
            'original_filename' => 'failed.pdf',
            'storage_path' => 'imports/failed.pdf',
            'status' => 'failed',
            'created_at' => '2026-04-14 09:00:00',
        ]);
        Import::query()->create([
            'user_id' => $user->id,
            'source_name' => 'mobile_suica',
            'original_filename' => 'recovered.pdf',
            'storage_path' => 'imports/recovered.pdf',
            'status' => 'validated',
            'created_at' => '2026-04-15 10:00:00',
        ]);
        Import::query()->create([
            'user_id' => User::factory()->create()->id,
            'source_name' => 'jre_point',
            'original_filename' => 'other-user.json',
            'storage_path' => 'imports/other-user.json',
            'status' => 'failed',
            'created_at' => '2026-04-15 11:00:00',
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('daily_snapshot_status.required_account_count', 1)
                ->where('daily_snapshot_status.updated_account_count', 0)
                ->has('daily_snapshot_status.accounts', 1)
                ->where('daily_snapshot_status.accounts.0.id', $staleBank->id)
                ->where('daily_snapshot_status.accounts.0.state', 'stale')
                ->where('daily_snapshot_status.accounts.0.latest_snapshot_date', '2026-04-14')
                ->where('daily_snapshot_status.accounts', fn ($accounts): bool => collect($accounts)->doesntContain('id', $neverCapturedCard->id))
                ->has('daily_snapshot_status.recent_failures', 1)
                ->where('daily_snapshot_status.recent_failures.0.source_name', 'balance_snapshot')
                ->where('daily_snapshot_status.recent_failures.0.error_message', '形式を確認してください。'));
    }

    public function test_dashboard_reports_weekly_jre_point_and_mobile_suica_status(): void
    {
        $user = User::factory()->create();
        $jrePointImport = Import::query()->create([
            'user_id' => $user->id,
            'source_name' => 'jre_point',
            'source_metadata' => ['captured_at' => '2026-04-14T20:00:00+09:00'],
            'original_filename' => 'jre-point.json',
            'storage_path' => 'imports/jre-point.json',
            'status' => 'imported',
            'imported_at' => '2026-04-14 20:05:00',
        ]);
        $jrePointImport->importRows()->create([
            'row_number' => 1,
            'raw_payload' => [],
            'transaction_date' => '2026-04-14',
            'status' => 'imported',
        ]);
        $mobileSuicaImport = Import::query()->create([
            'user_id' => $user->id,
            'source_name' => 'mobile_suica',
            'original_filename' => 'mobile-suica.pdf',
            'storage_path' => 'imports/mobile-suica.pdf',
            'status' => 'imported',
            'imported_at' => '2026-04-13 19:00:00',
        ]);
        $mobileSuicaImport->importRows()->create([
            'row_number' => 1,
            'raw_payload' => [],
            'transaction_date' => '2026-04-12',
            'status' => 'imported',
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('weekly_import_status.week_start', '2026-04-13')
                ->where('weekly_import_status.week_end', '2026-04-19')
                ->where('weekly_import_status.sources.jre_point.state', 'updated')
                ->where('weekly_import_status.sources.jre_point.latest_history_date', '2026-04-14')
                ->where('weekly_import_status.sources.mobile_suica.state', 'updated')
                ->where('weekly_import_status.sources.mobile_suica.latest_history_date', '2026-04-12'));
    }

    public function test_dashboard_weekly_import_status_does_not_use_other_users_imports(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        foreach (['jre_point', 'mobile_suica'] as $sourceName) {
            Import::query()->create([
                'user_id' => $otherUser->id,
                'source_name' => $sourceName,
                'source_metadata' => $sourceName === 'jre_point'
                    ? ['captured_at' => '2026-04-15T12:00:00+09:00']
                    : null,
                'original_filename' => $sourceName.'.json',
                'storage_path' => 'imports/'.$sourceName.'.json',
                'status' => 'imported',
                'imported_at' => '2026-04-15 12:00:00',
            ]);
        }

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('weekly_import_status.sources.jre_point.state', 'missing')
                ->where('weekly_import_status.sources.mobile_suica.state', 'missing'));
    }

    public function test_dashboard_reports_previous_week_imports_as_stale(): void
    {
        $user = User::factory()->create();

        foreach (['jre_point', 'mobile_suica'] as $sourceName) {
            Import::query()->create([
                'user_id' => $user->id,
                'source_name' => $sourceName,
                'source_metadata' => $sourceName === 'jre_point'
                    ? ['captured_at' => '2026-04-12T20:00:00+09:00']
                    : null,
                'original_filename' => $sourceName.'.json',
                'storage_path' => 'imports/'.$sourceName.'.json',
                'status' => 'imported',
                'imported_at' => '2026-04-12 20:00:00',
                'created_at' => '2026-04-12 20:00:00',
                'updated_at' => '2026-04-12 20:00:00',
            ]);
        }

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('weekly_import_status.sources.jre_point.state', 'stale')
                ->where('weekly_import_status.sources.mobile_suica.state', 'stale'));
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

    public function test_dashboard_monthly_report_compares_periods_and_summarizes_activity(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create(['currency' => 'JPY']);
        $incomeCategory = Category::factory()->for($user)->create(['type' => 'income']);
        $expenseCategory = Category::factory()->for($user)->create(['type' => 'expense']);

        foreach ([
            ['2026-04-03', 'income', '5000.00', 'Salary'],
            ['2026-04-05', 'expense', '1200.00', 'Supermarket'],
            ['2026-04-08', 'expense', '800.00', 'Cafe'],
            ['2026-03-03', 'income', '4000.00', 'Salary'],
            ['2026-03-05', 'expense', '1000.00', 'Supermarket'],
            ['2025-04-03', 'income', '2500.00', 'Salary'],
            ['2025-04-05', 'expense', '500.00', 'Cafe'],
        ] as [$date, $type, $amount, $merchant]) {
            Transaction::factory()->forAccount($account)->create([
                'user_id' => $user->id,
                'transaction_date' => $date,
                'type' => $type,
                'amount' => $amount,
                'merchant_name' => $merchant,
                'description' => null,
                'category_id' => $type === 'income' ? $incomeCategory->id : $expenseCategory->id,
            ]);
        }

        $this->actingAs($user)
            ->get(route('dashboard', ['month' => '2026-04']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('monthly_report.comparison_groups', 1)
                ->where('monthly_report.comparison_groups.0.currency', 'JPY')
                ->where('monthly_report.comparison_groups.0.current.income_total', '5000.00')
                ->where('monthly_report.comparison_groups.0.current.expense_total', '2000.00')
                ->where('monthly_report.comparison_groups.0.previous_month.label', '2026年3月')
                ->where('monthly_report.comparison_groups.0.previous_month.income_change_amount', '1000.00')
                ->where('monthly_report.comparison_groups.0.previous_month.income_change_percent', '25.0')
                ->where('monthly_report.comparison_groups.0.previous_month.expense_change_amount', '1000.00')
                ->where('monthly_report.comparison_groups.0.previous_month.expense_change_percent', '100.0')
                ->where('monthly_report.comparison_groups.0.previous_year.label', '2025年4月')
                ->where('monthly_report.comparison_groups.0.previous_year.income_change_percent', '100.0')
                ->where('monthly_report.comparison_groups.0.previous_year.expense_change_percent', '300.0')
                ->has('monthly_report.activity_groups', 1)
                ->where('monthly_report.activity_groups.0.transaction_count', 3)
                ->where('monthly_report.activity_groups.0.expense_count', 2)
                ->where('monthly_report.activity_groups.0.average_expense', '1000.00')
                ->where('monthly_report.activity_groups.0.largest_expense', '1200.00')
                ->has('monthly_report.top_merchants', 2)
                ->where('monthly_report.top_merchants.0.name', 'Supermarket')
                ->where('monthly_report.top_merchants.0.total_amount', '1200.00')
                ->where('monthly_report.top_merchants.1.name', 'Cafe')
                ->where('monthly_report.top_merchants.1.total_amount', '800.00'));
    }

    public function test_dashboard_monthly_report_explains_category_expense_changes(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create(['currency' => 'JPY']);
        $food = Category::factory()->for($user)->create(['name' => '食費', 'type' => 'expense']);
        $transport = Category::factory()->for($user)->create(['name' => '交通費', 'type' => 'expense']);
        $utilities = Category::factory()->for($user)->create(['name' => '水道・光熱費', 'type' => 'expense']);

        foreach ([
            ['2026-04-03', '1200.00', $food->id],
            ['2026-04-05', '800.00', $transport->id],
            ['2026-04-08', '200.00', null],
            ['2026-03-03', '500.00', $food->id],
            ['2026-03-05', '1000.00', $utilities->id],
            ['2026-03-08', '100.00', null],
        ] as [$date, $amount, $categoryId]) {
            Transaction::factory()->forAccount($account)->create([
                'user_id' => $user->id,
                'transaction_date' => $date,
                'type' => 'expense',
                'amount' => $amount,
                'currency' => 'JPY',
                'category_id' => $categoryId,
                'is_calculation_target' => true,
            ]);
        }

        Transaction::factory()->forAccount($account)->create([
            'user_id' => $user->id,
            'transaction_date' => '2026-04-10',
            'type' => 'expense',
            'amount' => '9999.00',
            'currency' => 'JPY',
            'category_id' => $food->id,
            'is_calculation_target' => false,
        ]);

        $otherUser = User::factory()->create();
        $otherAccount = Account::factory()->for($otherUser)->create(['currency' => 'JPY']);
        Transaction::factory()->forAccount($otherAccount)->create([
            'user_id' => $otherUser->id,
            'transaction_date' => '2026-04-10',
            'type' => 'expense',
            'amount' => '99999.00',
            'currency' => 'JPY',
            'is_calculation_target' => true,
        ]);

        $this->actingAs($user)
            ->get(route('dashboard', ['month' => '2026-04']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('monthly_report.category_expense_groups', 1)
                ->where('monthly_report.category_expense_groups.0.currency', 'JPY')
                ->where('monthly_report.category_expense_groups.0.previous_month_label', '2026年3月')
                ->where('monthly_report.category_expense_groups.0.current_total', '2200.00')
                ->where('monthly_report.category_expense_groups.0.previous_total', '1600.00')
                ->where('monthly_report.category_expense_groups.0.change_amount', '600.00')
                ->has('monthly_report.category_expense_groups.0.items', 4)
                ->where('monthly_report.category_expense_groups.0.items.0.category_name', '食費')
                ->where('monthly_report.category_expense_groups.0.items.0.current_amount', '1200.00')
                ->where('monthly_report.category_expense_groups.0.items.0.previous_amount', '500.00')
                ->where('monthly_report.category_expense_groups.0.items.0.change_amount', '700.00')
                ->where('monthly_report.category_expense_groups.0.items.0.current_share_percent', '54.5')
                ->where('monthly_report.category_expense_groups.0.items.1.category_name', '交通費')
                ->where('monthly_report.category_expense_groups.0.items.1.current_share_percent', '36.4')
                ->where('monthly_report.category_expense_groups.0.items.2.category_name', 'カテゴリ未設定')
                ->where('monthly_report.category_expense_groups.0.items.2.current_share_percent', '9.1')
                ->where('monthly_report.category_expense_groups.0.items.3.category_name', '水道・光熱費')
                ->where('monthly_report.category_expense_groups.0.items.3.current_amount', '0.00')
                ->where('monthly_report.category_expense_groups.0.items.3.previous_amount', '1000.00')
                ->where('monthly_report.category_expense_groups.0.items.3.change_amount', '-1000.00'));
    }

    public function test_dashboard_monthly_report_counts_only_the_users_review_items(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();

        Transaction::factory()->forAccount($account)->create([
            'user_id' => $user->id,
            'transaction_date' => '2026-04-05',
            'type' => 'expense',
            'amount' => '100.00',
            'category_id' => null,
            'is_confirmed' => false,
        ]);
        Transaction::factory()->forAccount($account)->create([
            'user_id' => $user->id,
            'transaction_date' => '2026-03-05',
            'type' => 'expense',
            'amount' => '100.00',
            'category_id' => null,
            'is_confirmed' => false,
        ]);

        foreach (['uploaded', 'validated', 'imported', 'failed'] as $status) {
            Import::query()->create([
                'user_id' => $user->id,
                'source_name' => 'test',
                'original_filename' => $status.'.csv',
                'storage_path' => 'imports/'.$status.'.csv',
                'status' => $status,
            ]);
        }

        $otherUser = User::factory()->create();
        $otherAccount = Account::factory()->for($otherUser)->create();
        Transaction::factory()->forAccount($otherAccount)->create([
            'user_id' => $otherUser->id,
            'transaction_date' => '2026-04-05',
            'category_id' => null,
            'is_confirmed' => false,
        ]);
        Import::query()->create([
            'user_id' => $otherUser->id,
            'source_name' => 'test',
            'original_filename' => 'other.csv',
            'storage_path' => 'imports/other.csv',
            'status' => 'uploaded',
        ]);

        $this->actingAs($user)
            ->get(route('dashboard', ['month' => '2026-04']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('monthly_report.quality.uncategorized_count', 1)
                ->where('monthly_report.quality.unconfirmed_count', 1)
                ->where('monthly_report.quality.pending_import_count', 2));
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
                ->where('monthly_report', null)
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
                ->where('yearly_category_expenses.0.items.1.category_name', 'カテゴリ未設定')
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
                ->where('category_expenses.1.name', 'カテゴリ未設定')
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
