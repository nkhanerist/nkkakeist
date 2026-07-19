<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Category;
use App\Models\MonthlyClosing;
use App\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MonthlyClosingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-07-19 12:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_dashboard_lists_only_the_users_required_accounts_for_monthly_closing(): void
    {
        $user = User::factory()->create();
        $requiredAccount = Account::factory()->for($user)->create([
            'name' => 'Dカード',
            'type' => 'credit_card',
            'monthly_close_required' => true,
            'is_active' => true,
        ]);
        Account::factory()->for($user)->create([
            'name' => '現金',
            'monthly_close_required' => false,
        ]);
        Account::factory()->for(User::factory()->create())->create([
            'name' => '他ユーザーの銀行',
            'monthly_close_required' => true,
        ]);

        $this->actingAs($user)
            ->get(route('dashboard', ['month' => '2026-06']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('monthly_report.closing.status', 'open')
                ->where('monthly_report.closing.status_label', '受付中')
                ->where('monthly_report.closing.month_ended', true)
                ->where('monthly_report.closing.can_close', false)
                ->has('monthly_report.closing.accounts', 1)
                ->where('monthly_report.closing.accounts.0.id', $requiredAccount->id)
                ->where('monthly_report.closing.accounts.0.state', 'unconfirmed'));
    }

    public function test_user_can_review_confirm_accounts_close_and_reopen_a_past_month(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create([
            'name' => 'THEO',
            'type' => 'securities',
            'monthly_close_required' => true,
            'is_active' => true,
        ]);
        $category = Category::factory()->for($user)->create(['type' => 'expense']);
        Transaction::factory()
            ->forAccount($account)
            ->forCategory($category)
            ->create([
                'user_id' => $user->id,
                'transaction_date' => '2026-06-10',
                'type' => 'expense',
                'is_confirmed' => true,
                'is_calculation_target' => true,
            ]);

        $this->actingAs($user)
            ->patch(route('monthly-closings.update', '2026-06'), [
                'note' => 'カード請求と積立を確認する',
            ])
            ->assertRedirect();

        $this->actingAs($user)
            ->put(route('monthly-closings.accounts.confirm', [
                'month' => '2026-06',
                'account' => $account,
            ]))
            ->assertRedirect();

        $this->actingAs($user)
            ->post(route('monthly-closings.review', '2026-06'))
            ->assertRedirect();

        $this->actingAs($user)
            ->get(route('dashboard', ['month' => '2026-06']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('monthly_report.closing.status', 'reviewed')
                ->where('monthly_report.closing.note', 'カード請求と積立を確認する')
                ->where('monthly_report.closing.has_changes_since_review', false)
                ->where('monthly_report.closing.accounts.0.state', 'confirmed')
                ->where('monthly_report.closing.can_close', true)
                ->has('monthly_report.closing.blockers', 0));

        $this->actingAs($user)
            ->post(route('monthly-closings.close', '2026-06'))
            ->assertRedirect();

        $closing = MonthlyClosing::query()
            ->where('user_id', $user->id)
            ->whereDate('month', '2026-06-01')
            ->sole();
        self::assertSame('closed', $closing->status);
        self::assertSame('カード請求と積立を確認する', $closing->note);

        $this->actingAs($user)
            ->post(route('monthly-closings.reopen', '2026-06'), [
                'reason' => '電話料金の後着明細を取り込むため',
            ])
            ->assertRedirect();

        $closing->refresh();
        self::assertSame('open', $closing->status);
        self::assertSame('電話料金の後着明細を取り込むため', $closing->last_reopen_reason);
    }

    public function test_late_account_data_is_detected_after_review_and_close(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create([
            'name' => 'Dカード',
            'type' => 'credit_card',
            'monthly_close_required' => true,
        ]);
        $category = Category::factory()->for($user)->create(['type' => 'expense']);

        $this->actingAs($user)
            ->put(route('monthly-closings.accounts.confirm', [
                'month' => '2026-06',
                'account' => $account,
            ]));
        $this->actingAs($user)
            ->post(route('monthly-closings.review', '2026-06'));
        $this->actingAs($user)
            ->post(route('monthly-closings.close', '2026-06'))
            ->assertRedirect();

        Transaction::factory()
            ->forAccount($account)
            ->forCategory($category)
            ->create([
                'user_id' => $user->id,
                'transaction_date' => '2026-06-30',
                'description' => '後から反映された電話料金',
                'type' => 'expense',
                'is_confirmed' => true,
                'is_calculation_target' => true,
            ]);

        $this->actingAs($user)
            ->get(route('dashboard', ['month' => '2026-06']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('monthly_report.closing.status', 'closed')
                ->where('monthly_report.closing.has_changes_since_review', true)
                ->where('monthly_report.closing.accounts.0.state', 'changed')
                ->where('monthly_report.closing.can_close', false));

        $this->actingAs($user)
            ->put(route('monthly-closings.accounts.confirm', [
                'month' => '2026-06',
                'account' => $account,
            ]))
            ->assertSessionHasErrors('account');
    }

    public function test_month_cannot_be_closed_until_all_guards_are_satisfied(): void
    {
        $user = User::factory()->create();
        Account::factory()->for($user)->create([
            'name' => 'ソニー銀行',
            'type' => 'bank',
            'monthly_close_required' => true,
        ]);

        $this->actingAs($user)
            ->post(route('monthly-closings.review', '2026-06'))
            ->assertRedirect();

        $this->actingAs($user)
            ->post(route('monthly-closings.close', '2026-06'))
            ->assertSessionHasErrors('monthly_closing');

        self::assertSame(
            'reviewed',
            MonthlyClosing::query()
                ->where('user_id', $user->id)
                ->whereDate('month', '2026-06-01')
                ->sole()
                ->status,
        );

        $this->actingAs($user)
            ->post(route('monthly-closings.review', '2026-07'));
        $this->actingAs($user)
            ->post(route('monthly-closings.close', '2026-07'))
            ->assertSessionHasErrors('monthly_closing');
    }

    public function test_user_cannot_confirm_another_users_account(): void
    {
        $user = User::factory()->create();
        $otherAccount = Account::factory()->for(User::factory()->create())->create([
            'monthly_close_required' => true,
        ]);

        $this->actingAs($user)
            ->put(route('monthly-closings.accounts.confirm', [
                'month' => '2026-06',
                'account' => $otherAccount,
            ]))
            ->assertNotFound();

        $this->assertDatabaseCount('monthly_close_account_confirmations', 0);
        $this->assertDatabaseCount('monthly_closings', 0);
    }

    public function test_monthly_closing_routes_reject_invalid_months(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/monthly-closings/2026-13/review')
            ->assertNotFound();

        $this->assertDatabaseCount('monthly_closings', 0);
    }
}
