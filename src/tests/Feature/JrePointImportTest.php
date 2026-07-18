<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountSnapshot;
use App\Models\Import;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Accounts\AccountBalanceCalculatorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class JrePointImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    public function test_user_can_reconcile_and_commit_jre_point_history(): void
    {
        $user = User::factory()->create();
        $jrePoint = Account::factory()->for($user)->create([
            'name' => 'JREポイント',
            'type' => 'point',
            'currency' => 'JPY',
            'initial_balance' => '5597.00',
        ]);
        $mobileSuica = Account::factory()->for($user)->create([
            'name' => 'モバイルSuica',
            'type' => 'e_money',
            'currency' => 'JPY',
        ]);

        foreach ([
            ['2026-03-25', '3541.00'],
            ['2026-04-18', '1412.00'],
            ['2026-05-30', '644.00'],
        ] as [$date, $amount]) {
            Transaction::factory()->transfer($jrePoint, $mobileSuica)->create([
                'transaction_date' => $date,
                'amount' => $amount,
                'currency' => 'JPY',
                'merchant_name' => 'JREポイントチャージ',
                'description' => 'JREポイント → モバイルSuica',
                'is_calculation_target' => false,
                'affects_account_balance' => true,
            ]);
        }

        $this->actingAs($user)
            ->post(route('imports.store'), $this->uploadPayload($jrePoint))
            ->assertRedirect();

        $import = Import::query()->firstOrFail();

        $this->assertDatabaseHas('imports', [
            'id' => $import->id,
            'source_name' => 'jre_point',
            'status' => 'validated',
            'total_rows' => 4,
            'duplicate_rows' => 3,
        ]);
        $this->assertDatabaseHas('import_rows', [
            'import_id' => $import->id,
            'transaction_date' => '2026-07-13 00:00:00',
            'amount' => '6194.00',
            'detected_type' => 'income',
            'resolved_is_calculation_target' => 0,
            'resolved_affects_account_balance' => 1,
            'is_duplicate_candidate' => 0,
        ]);
        $this->assertDatabaseHas('import_rows', [
            'import_id' => $import->id,
            'transaction_date' => '2026-03-25 00:00:00',
            'amount' => '3541.00',
            'detected_type' => 'transfer',
            'resolved_account_id' => $jrePoint->id,
            'resolved_transfer_account_id' => $mobileSuica->id,
            'is_duplicate_candidate' => 1,
        ]);

        $this->actingAs($user)
            ->get(route('imports.show', $import))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Imports/Show')
                ->where('jrePointReconciliation.official_total', '597.00')
                ->where('jrePointReconciliation.ledger_balance_before_import', '0.00')
                ->where('jrePointReconciliation.import_balance_change', '6194.00')
                ->where('jrePointReconciliation.expected_balance_after_import', '6194.00')
                ->where('jrePointReconciliation.difference', '5597.00')
                ->where('jrePointReconciliation.is_initial_import', true)
                ->where('jrePointReconciliation.recommended_initial_balance', '0.00'));

        $this->actingAs($user)
            ->post(route('imports.commit', $import))
            ->assertRedirect(route('imports.show', $import));

        self::assertSame('0.00', (string) $jrePoint->fresh()->initial_balance);
        self::assertSame(4, Transaction::query()->where('user_id', $user->id)->count());
        $this->assertDatabaseHas('transactions', [
            'import_id' => $import->id,
            'account_id' => $jrePoint->id,
            'type' => 'income',
            'amount' => '6194.00',
            'is_calculation_target' => 0,
            'affects_account_balance' => 1,
        ]);
        $this->assertDatabaseHas('categories', [
            'user_id' => $user->id,
            'name' => '収入',
            'type' => 'income',
        ]);
        $this->assertDatabaseHas('subcategories', [
            'user_id' => $user->id,
            'name' => 'ポイント獲得',
        ]);

        $snapshot = AccountSnapshot::query()->where('import_id', $import->id)->firstOrFail();
        self::assertSame('597.00', (string) $snapshot->balance);
        self::assertSame('597.00', $snapshot->metadata['regular_points']);
        self::assertSame('0.00', $snapshot->metadata['limited_points']);
        self::assertTrue($snapshot->metadata['initial_balance_rebased']);

        $currentBalance = app(AccountBalanceCalculatorService::class)->calculate(
            $jrePoint->fresh(),
            '2026-07-31',
        );
        self::assertSame('597.00', $currentBalance);
    }

    public function test_reimport_marks_all_jre_point_rows_as_duplicates_without_rebasing_again(): void
    {
        $user = User::factory()->create();
        $jrePoint = Account::factory()->for($user)->create([
            'name' => 'JREポイント',
            'type' => 'point',
            'initial_balance' => '0.00',
        ]);
        Account::factory()->for($user)->create([
            'name' => 'モバイルSuica',
            'type' => 'e_money',
        ]);

        $this->actingAs($user)->post(route('imports.store'), $this->uploadPayload($jrePoint));
        $firstImport = Import::query()->firstOrFail();
        $this->actingAs($user)->post(route('imports.commit', $firstImport));

        $this->actingAs($user)->post(route('imports.store'), $this->uploadPayload($jrePoint));
        $secondImport = Import::query()->latest('id')->firstOrFail();

        self::assertSame(4, $secondImport->duplicate_rows);

        $this->actingAs($user)
            ->get(route('imports.show', $secondImport))
            ->assertInertia(fn (Assert $page) => $page
                ->where('jrePointReconciliation.is_initial_import', false)
                ->where('jrePointReconciliation.import_balance_change', '0.00'));
    }

    public function test_jre_point_import_requires_users_point_account(): void
    {
        $user = User::factory()->create();
        $bankAccount = Account::factory()->for($user)->create(['type' => 'bank']);
        $otherUsersPoint = Account::factory()->for(User::factory()->create())->create(['type' => 'point']);

        $this->actingAs($user)
            ->post(route('imports.store'), $this->uploadPayload($bankAccount))
            ->assertSessionHasErrors('account_id');

        $this->actingAs($user)
            ->post(route('imports.store'), $this->uploadPayload($otherUsersPoint))
            ->assertSessionHasErrors('account_id');
    }

    public function test_jre_point_import_with_unresolved_charge_cannot_be_committed(): void
    {
        $user = User::factory()->create();
        $jrePoint = Account::factory()->for($user)->create([
            'name' => 'JREポイント',
            'type' => 'point',
            'initial_balance' => '0.00',
        ]);

        $this->actingAs($user)->post(route('imports.store'), $this->uploadPayload($jrePoint));
        $import = Import::query()->firstOrFail();

        $this->assertDatabaseHas('import_rows', [
            'import_id' => $import->id,
            'detected_type' => 'transfer',
            'status' => 'error',
        ]);

        $this->actingAs($user)
            ->post(route('imports.commit', $import))
            ->assertRedirect(route('imports.show', $import))
            ->assertSessionHas(
                'error',
                'JRE POINT取込に未解決の行があります。すべて解決してから確定してください。',
            );

        $this->assertDatabaseHas('imports', [
            'id' => $import->id,
            'status' => 'validated',
            'imported_rows' => 0,
        ]);
        self::assertSame('0.00', (string) $jrePoint->fresh()->initial_balance);
    }

    /** @return array{source_name:string, account_id:int, csv_file:UploadedFile} */
    private function uploadPayload(Account $account): array
    {
        return [
            'source_name' => 'jre_point',
            'account_id' => $account->id,
            'csv_file' => UploadedFile::fake()->createWithContent(
                'jre-point-history.json',
                json_encode($this->payload(), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR),
            ),
        ];
    }

    /** @return array<string, mixed> */
    private function payload(): array
    {
        return [
            'format' => 'nkkakeist-jre-point-history',
            'version' => 1,
            'captured_at' => '2026-07-18T14:00:00+09:00',
            'balance' => [
                'total' => 597,
                'limited' => 0,
                'regular' => 597,
                'nearest_expiry' => '2028-07-31',
            ],
            'page_count' => 5,
            'rows' => [
                [
                    'reflection_date' => '2026-07-13',
                    'place' => 'ＪＲ東日本',
                    'description' => 'ポイント獲得',
                    'points' => 6194,
                    'source_icon' => 'ico-train-x32.svg',
                ],
                [
                    'reflection_date' => '2026-03-26',
                    'actual_date' => '2026-03-25',
                    'place' => 'モバイルＳｕｉｃａ',
                    'description' => '３／２５ チャージ',
                    'points' => -3541,
                    'source_icon' => 'ico-app.svg',
                ],
                [
                    'reflection_date' => '2026-04-19',
                    'actual_date' => '2026-04-18',
                    'place' => 'モバイルＳｕｉｃａ',
                    'description' => '４／１８ チャージ',
                    'points' => -1412,
                    'source_icon' => 'ico-app.svg',
                ],
                [
                    'reflection_date' => '2026-05-31',
                    'actual_date' => '2026-05-30',
                    'place' => 'モバイルＳｕｉｃａ',
                    'description' => '５／３０ チャージ',
                    'points' => -644,
                    'source_icon' => 'ico-app.svg',
                ],
            ],
        ];
    }
}
