<?php

namespace Tests\Feature;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportMoneyForwardDailyStatusCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-07-18 21:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_command_reports_missing_status_without_updating_data(): void
    {
        $user = User::factory()->create();

        $this->artisan('money-forward:daily-status', ['--user' => (string) $user->id])
            ->expectsTable(
                ['ユーザーID', '対象日', '状態', '口座', '銘柄', '総資産', '最終取込'],
                [[$user->id, '2026-07-18', 'missing', 0, 0, 'missing', '-']],
            )
            ->assertSuccessful();

        self::assertSame(0, $user->accountSnapshots()->count());
        self::assertSame(0, $user->assetHistorySnapshots()->count());
    }

    public function test_command_fails_when_requested_user_does_not_exist(): void
    {
        $this->artisan('money-forward:daily-status', ['--user' => 'missing@example.com'])
            ->expectsOutput('確認対象のユーザーが見つかりません。')
            ->assertFailed();
    }
}
