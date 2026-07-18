<?php

namespace Tests\Feature;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportWeeklyImportStatusCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-07-19 20:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_command_reports_missing_weekly_sources_without_updating_data(): void
    {
        $user = User::factory()->create();

        $this->artisan('imports:weekly-status', ['--user' => (string) $user->id])
            ->expectsTable(
                ['ユーザーID', '対象週', 'JRE POINT', 'モバイルSuica', 'JRE最終更新', 'Suica最終更新'],
                [[$user->id, '2026-07-13〜2026-07-19', 'missing', 'missing', '-', '-']],
            )
            ->assertSuccessful();

        self::assertSame(0, $user->imports()->count());
    }

    public function test_command_fails_when_requested_user_does_not_exist(): void
    {
        $this->artisan('imports:weekly-status', ['--user' => 'missing@example.com'])
            ->expectsOutput('確認対象のユーザーが見つかりません。')
            ->assertFailed();
    }
}
