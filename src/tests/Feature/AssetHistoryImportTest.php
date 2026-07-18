<?php

namespace Tests\Feature;

use App\Models\AssetHistorySnapshot;
use App\Models\Import;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AssetHistoryImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_preview_commit_and_delete_money_forward_asset_history(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('imports.store'), $this->uploadPayload())
            ->assertRedirect();

        $import = Import::query()->sole();

        $this->actingAs($user)
            ->get(route('imports.show', $import))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Imports/Show')
                ->where('import.source_name', 'asset_history')
                ->has('rows', 2)
                ->where('rows.0.transaction_date', '2026-06-30')
                ->where('rows.0.amount', '21000000.00')
                ->where('rows.0.raw_payload.breakdown.投資信託', '14300000.00')
                ->where('rows.0.status', 'ready'));

        $this->actingAs($user)
            ->post(route('imports.commit', $import))
            ->assertSessionHasNoErrors();

        self::assertSame(2, AssetHistorySnapshot::query()->count());
        $latest = AssetHistorySnapshot::query()->orderByDesc('captured_on')->firstOrFail();
        self::assertSame('22667260.00', (string) $latest->total_assets);
        self::assertSame('15525499.00', $latest->breakdown['投資信託']);

        $this->actingAs($user)
            ->delete(route('imports.destroy', $import))
            ->assertRedirect(route('imports.index'));

        self::assertSame(0, AssetHistorySnapshot::query()->count());
    }

    public function test_reimport_marks_existing_dates_as_duplicate_candidates(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('imports.store'), $this->uploadPayload('first.csv'));
        $first = Import::query()->sole();
        $this->actingAs($user)
            ->post(route('imports.commit', $first))
            ->assertSessionHasNoErrors();
        self::assertSame('imported', $first->refresh()->status);
        self::assertSame(2, AssetHistorySnapshot::query()->count());

        $this->actingAs($user)
            ->post(route('imports.store'), $this->uploadPayload('second.csv'))
            ->assertRedirect()
            ->assertSessionHasNoErrors();
        self::assertSame(2, Import::query()->count());
        $second = Import::query()->orderByDesc('id')->firstOrFail();

        self::assertSame('validated', $second->status, (string) $second->error_message);
        self::assertTrue($second->importRows()->get()->every('is_duplicate_candidate'));
        self::assertSame(2, $second->duplicate_rows);
    }

    /** @return array<string, mixed> */
    private function uploadPayload(string $filename = 'asset-history.csv'): array
    {
        $csv = implode("\r\n", [
            '"日付","合計（円）","預金・現金（円）","投資信託（円）","年金（円）","ポイント（円）"',
            '"2026/06/30","21,000,000","5,000,000","14,300,000","1,690,000","10,000"',
            '"2026/07/18","22,667,260","5,300,726","15,525,499","1,832,820","8,215"',
        ]);

        return [
            'source_name' => 'asset_history',
            'account_id' => '',
            'csv_file' => UploadedFile::fake()->createWithContent(
                $filename,
                mb_convert_encoding($csv, 'CP932', 'UTF-8'),
            ),
        ];
    }
}
