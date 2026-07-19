<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountSnapshot;
use App\Models\Import;
use App\Models\InvestmentPositionSnapshot;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SecuritiesOverviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-07-18 12:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_user_can_view_account_and_position_valuation_trends(): void
    {
        $user = User::factory()->create();
        $theo = Account::factory()->for($user)->create([
            'name' => 'THEO',
            'type' => 'securities',
            'balance_method' => 'snapshot',
            'currency' => 'JPY',
            'display_order' => 1,
        ]);
        $sony = Account::factory()->for($user)->create([
            'name' => 'ソニー銀行 投資信託',
            'type' => 'securities',
            'balance_method' => 'snapshot',
            'currency' => 'JPY',
            'display_order' => 2,
        ]);
        $import = $this->createImport($user, 'own.json');
        $theoFirst = $this->createSnapshot($user, $theo, $import, '2026-07-17', '10000.00');
        $theoLatest = $this->createSnapshot($user, $theo, $import, '2026-07-18', '12000.00');
        $this->createSnapshot($user, $sony, $import, '2026-07-18', '5000.00');
        $this->createPosition($user, $theo, $theoFirst, $import, 'growth', 'グロース株式', '4000.00');
        $this->createPosition($user, $theo, $theoLatest, $import, 'growth', 'グロース株式', '5200.00');

        $otherUser = User::factory()->create();
        $otherAccount = Account::factory()->for($otherUser)->create([
            'name' => 'Other Securities',
            'type' => 'securities',
            'balance_method' => 'snapshot',
        ]);
        $otherImport = $this->createImport($otherUser, 'other.json');
        $this->createSnapshot($otherUser, $otherAccount, $otherImport, '2026-07-18', '999999.00');

        $this->actingAs($user)
            ->get(route('securities.index', ['period' => '30d']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Securities/Index')
                ->where('selected_period', '30d')
                ->where('period_label', '30日')
                ->has('accounts', 2)
                ->where('accounts.0.id', $theo->id)
                ->where('accounts.0.latest_valuation', '12000.00')
                ->where('accounts.1.id', $sony->id)
                ->has('account_series', 2)
                ->has('account_series.0.points', 2)
                ->where('account_series.0.points.1.value', '12000.00')
                ->has('position_groups', 2)
                ->has('position_groups.0.series', 1)
                ->where('position_groups.0.series.0.label', 'グロース株式')
                ->has('position_groups.0.series.0.points', 2)
                ->where('position_groups.0.series.0.points.1.value', '5200.00'));
    }

    public function test_unknown_period_falls_back_to_ninety_days(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('securities.index', ['period' => 'invalid']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('selected_period', '90d')
                ->where('period_label', '90日'));
    }

    public function test_user_can_drill_down_into_securities_account_and_position_history(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create([
            'name' => 'THEO',
            'type' => 'securities',
            'balance_method' => 'snapshot',
            'currency' => 'JPY',
        ]);
        $import = $this->createImport($user, 'detail.json');
        $first = $this->createSnapshot($user, $account, $import, '2026-07-17', '10000.00');
        $latest = $this->createSnapshot($user, $account, $import, '2026-07-18', '12000.00');
        $this->createPosition(
            $user,
            $account,
            $first,
            $import,
            'growth',
            'グロース株式',
            '4000.00',
            [
                'instrument_code' => 'GROWTH',
                'asset_class' => '株式',
                'quantity' => '10.00000000',
                'average_acquisition_price' => '350.000000',
                'unit_price' => '400.000000',
                'acquisition_cost' => '3500.00',
                'unrealized_gain' => '500.00',
            ],
        );
        $this->createPosition(
            $user,
            $account,
            $latest,
            $import,
            'growth',
            'グロース株式',
            '5200.00',
            [
                'instrument_code' => 'GROWTH',
                'asset_class' => '株式',
                'quantity' => '10.00000000',
                'average_acquisition_price' => '350.000000',
                'unit_price' => '520.000000',
                'acquisition_cost' => '3500.00',
                'unrealized_gain' => '1700.00',
            ],
        );

        $this->actingAs($user)
            ->get(route('securities.show', [
                'account' => $account,
                'period' => '30d',
                'position' => 'growth',
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Securities/Show')
                ->where('selected_period', '30d')
                ->where('account.id', $account->id)
                ->where('account.latest_valuation', '12000.00')
                ->where('account.snapshot_count', 2)
                ->where('account.change_amount', '2000.00')
                ->has('account_series.points', 2)
                ->has('snapshots', 2)
                ->where('snapshots.0.date', '2026-07-18')
                ->where('snapshots.0.change_amount', '2000.00')
                ->where('snapshots.0.position_count', 1)
                ->where('positions_as_of_date', '2026-07-18')
                ->has('latest_positions', 1)
                ->where('latest_positions.0.instrument_name', 'グロース株式')
                ->where('latest_positions.0.valuation', '5200.00')
                ->where('latest_positions.0.acquisition_cost', '3500.00')
                ->where('latest_positions.0.change_amount', '1200.00')
                ->where('latest_positions.0.share_percent', '100.0')
                ->where('selected_position_key', 'growth')
                ->where('selected_position.instrument_code', 'GROWTH')
                ->where('selected_position.latest.acquisition_cost', '3500.00')
                ->where('selected_position.latest.unrealized_gain', '1700.00')
                ->has('selected_position.series.points', 2)
                ->has('selected_position.comparison_series', 2)
                ->where('selected_position.comparison_series.0.label', '評価額')
                ->where('selected_position.comparison_series.0.points.1.value', '5200.00')
                ->where('selected_position.comparison_series.1.label', '取得価額')
                ->where('selected_position.comparison_series.1.points.1.value', '3500.00')
                ->has('selected_position.history', 2)
                ->where('selected_position.history.0.acquisition_cost', '3500.00')
                ->where('selected_position.history.0.change_amount', '1200.00'));
    }

    public function test_securities_labels_follow_the_selected_locale(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create([
            'name' => 'Test Securities',
            'type' => 'securities',
            'balance_method' => 'snapshot',
            'currency' => 'JPY',
        ]);
        $import = $this->createImport($user, 'localized.json');
        $snapshot = $this->createSnapshot(
            $user,
            $account,
            $import,
            '2026-07-18',
            '12000.00',
        );
        $this->createPosition(
            $user,
            $account,
            $snapshot,
            $import,
            'fund',
            'User Fund Name',
            '12000.00',
            ['acquisition_cost' => '10000.00'],
        );

        $this->actingAs($user)
            ->withSession(['locale' => 'en'])
            ->get(route('securities.index', ['period' => '30d']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('period_label', '30 Days')
                ->where('period_options.0.label', '30 Days')
                ->where('period_options.2.label', '1 Year')
                ->where('period_options.3.label', 'All Time')
                ->where('position_groups.0.series.0.label', 'User Fund Name'));

        $this->actingAs($user)
            ->withSession(['locale' => 'en'])
            ->get(route('securities.show', [
                'account' => $account,
                'period' => '30d',
                'position' => 'fund',
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('period_label', '30 Days')
                ->where('selected_position.instrument_name', 'User Fund Name')
                ->where('selected_position.comparison_series.0.label', 'Valuation')
                ->where('selected_position.comparison_series.1.label', 'Acquisition Cost'));
    }

    public function test_user_cannot_view_other_users_securities_detail(): void
    {
        $user = User::factory()->create();
        $otherAccount = Account::factory()->for(User::factory()->create())->create([
            'type' => 'securities',
        ]);

        $this->actingAs($user)
            ->get(route('securities.show', $otherAccount))
            ->assertForbidden();
    }

    public function test_non_securities_account_does_not_have_securities_detail(): void
    {
        $user = User::factory()->create();
        $bank = Account::factory()->for($user)->create(['type' => 'bank']);

        $this->actingAs($user)
            ->get(route('securities.show', $bank))
            ->assertNotFound();
    }

    private function createImport(User $user, string $filename): Import
    {
        return Import::query()->create([
            'user_id' => $user->id,
            'source_name' => 'balance_snapshot',
            'original_filename' => $filename,
            'storage_path' => "imports/{$filename}",
            'status' => 'imported',
            'total_rows' => 1,
            'imported_rows' => 1,
        ]);
    }

    private function createSnapshot(
        User $user,
        Account $account,
        Import $import,
        string $date,
        string $balance,
    ): AccountSnapshot {
        return AccountSnapshot::query()->create([
            'user_id' => $user->id,
            'account_id' => $account->id,
            'import_id' => $import->id,
            'captured_at' => "{$date} 23:59:59",
            'purpose' => 'valuation',
            'balance' => $balance,
            'source_name' => 'Money Forward',
        ]);
    }

    private function createPosition(
        User $user,
        Account $account,
        AccountSnapshot $snapshot,
        Import $import,
        string $key,
        string $name,
        string $valuation,
        array $attributes = [],
    ): InvestmentPositionSnapshot {
        return InvestmentPositionSnapshot::query()->create([
            'user_id' => $user->id,
            'account_id' => $account->id,
            'account_snapshot_id' => $snapshot->id,
            'import_id' => $import->id,
            'captured_at' => $snapshot->captured_at,
            'position_key' => $key,
            'instrument_name' => $name,
            'valuation' => $valuation,
            'currency' => $account->currency,
            'source_name' => 'Money Forward',
            ...$attributes,
        ]);
    }
}
