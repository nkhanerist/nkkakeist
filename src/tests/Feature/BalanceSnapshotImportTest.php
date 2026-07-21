<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountSnapshot;
use App\Models\AssetHistorySnapshot;
use App\Models\Import;
use App\Models\InvestmentPositionSnapshot;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class BalanceSnapshotImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_preview_and_commit_multiple_official_balances(): void
    {
        $user = User::factory()->create();
        $theo = Account::factory()->for($user)->create([
            'name' => 'THEO',
            'type' => 'securities',
            'balance_role' => 'asset',
            'balance_method' => 'snapshot',
            'currency' => 'JPY',
            'initial_balance' => '0.00',
        ]);
        $sonyFund = Account::factory()->for($user)->create([
            'name' => 'ソニー銀行 投資信託',
            'type' => 'securities',
            'balance_role' => 'asset',
            'balance_method' => 'snapshot',
            'currency' => 'JPY',
            'initial_balance' => '0.00',
        ]);
        $card = Account::factory()->for($user)->create([
            'name' => 'dカード',
            'type' => 'credit_card',
            'balance_role' => 'liability',
            'balance_method' => 'ledger',
            'currency' => 'JPY',
            'initial_balance' => '0.00',
        ]);

        $this->actingAs($user)
            ->post(route('imports.store'), $this->uploadPayload())
            ->assertRedirect();

        $import = Import::query()->sole();

        $this->actingAs($user)
            ->get(route('imports.show', $import))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Imports/Show')
                ->where('import.source_name', 'balance_snapshot')
                ->where('import.source_metadata.acquisition_diagnostics.exporter_version', 2)
                ->where('import.source_metadata.acquisition_diagnostics.portfolio_summary_table', true)
                ->has('rows', 3)
                ->where('rows.0.resolved_account.id', $theo->id)
                ->where('rows.0.amount', '412345.67')
                ->where('rows.1.resolved_account.id', $sonyFund->id)
                ->where('rows.2.resolved_account.id', $card->id)
                ->where('rows.2.amount', '-65940.00')
                ->where('rows.2.status', 'ready'));

        $this->actingAs($user)
            ->post(route('imports.commit', $import))
            ->assertRedirect(route('imports.show', $import));

        $import->refresh();
        self::assertSame('imported', $import->status);
        self::assertSame(3, $import->imported_rows);
        self::assertSame(3, AccountSnapshot::query()->count());
        self::assertSame(2, InvestmentPositionSnapshot::query()->count());
        self::assertSame(1, AssetHistorySnapshot::query()->count());
        self::assertSame(0, Transaction::query()->count());
        self::assertSame('0.00', (string) $card->refresh()->initial_balance);

        $this->assertDatabaseHas('account_snapshots', [
            'account_id' => $theo->id,
            'import_id' => $import->id,
            'purpose' => 'valuation',
            'balance' => '412345.67',
            'source_name' => 'Money Forward',
        ]);
        $cardSnapshot = AccountSnapshot::query()->where('account_id', $card->id)->sole();
        self::assertSame('official_balance', $cardSnapshot->purpose);
        self::assertSame('-65940.00', (string) $cardSnapshot->balance);
        self::assertSame('42000.00', $cardSnapshot->metadata['next_payment_amount']);
        self::assertSame('2026-08-10', $cardSnapshot->metadata['next_payment_date']);
        $theoPosition = InvestmentPositionSnapshot::query()
            ->where('account_id', $theo->id)
            ->where('instrument_name', 'グロース株式')
            ->sole();
        self::assertSame('123.45678901', (string) $theoPosition->quantity);
        self::assertSame('206200.00', (string) $theoPosition->acquisition_cost);
        self::assertSame('205000.00', (string) $theoPosition->valuation);
        self::assertSame('-1200.00', (string) $theoPosition->unrealized_gain);
        self::assertSame($import->id, $theoPosition->import_id);
    }

    public function test_user_can_import_a_money_forward_bank_balance(): void
    {
        $user = User::factory()->create();
        $bank = Account::factory()->for($user)->create([
            'name' => 'ソニー銀行',
            'type' => 'bank',
            'balance_role' => 'asset',
            'balance_method' => 'ledger',
            'currency' => 'JPY',
        ]);
        $payload = $this->payload();
        $payload['items'] = [[
            'source_account_name' => 'ソニー銀行',
            'balance_kind' => 'account_balance',
            'balance' => '345678',
            'currency' => 'JPY',
            'balance_date' => '2026-07-18',
            'source_details' => ['普通預金'],
        ]];

        $this->actingAs($user)->post(route('imports.store'), [
            'source_name' => 'balance_snapshot',
            'account_id' => '',
            'csv_file' => UploadedFile::fake()->createWithContent(
                'money-forward-bank.json',
                json_encode($payload, JSON_THROW_ON_ERROR),
            ),
        ]);
        $import = Import::query()->sole();
        $row = $import->importRows()->sole();

        self::assertSame($bank->id, $row->resolved_account_id);
        self::assertSame('ready', $row->status);

        $this->actingAs($user)
            ->post(route('imports.commit', $import))
            ->assertSessionHasNoErrors();

        $snapshot = AccountSnapshot::query()->sole();
        self::assertSame('official_balance', $snapshot->purpose);
        self::assertSame('345678.00', (string) $snapshot->balance);
        self::assertSame('account_balance', $snapshot->metadata['balance_kind']);
        self::assertSame(['普通預金'], $snapshot->metadata['source_details']);
    }

    public function test_user_can_import_jis_and_t_pension_valuation_with_positions(): void
    {
        $user = User::factory()->create();
        $pension = Account::factory()->for($user)->create([
            'name' => 'JIS&T(確定拠出年金)',
            'type' => 'securities',
            'balance_role' => 'asset',
            'balance_method' => 'snapshot',
            'currency' => 'JPY',
            'initial_balance' => '0.00',
            'import_aliases' => ['Money Forward 年金'],
        ]);
        $payload = $this->payload();
        $payload['items'] = [[
            'source_account_name' => 'Money Forward 年金',
            'balance_kind' => 'valuation',
            'balance' => '1832820',
            'currency' => 'JPY',
            'balance_date' => '2026-07-18',
            'positions' => [
                [
                    'instrument_name' => '信託のチカラ日本の株式',
                    'external_id' => 'money_forward:pension:信託のチカラ日本の株式',
                    'asset_class' => 'defined_contribution_pension',
                    'acquisition_cost' => '475430',
                    'valuation' => '1042090',
                    'unrealized_gain' => '566660',
                    'currency' => 'JPY',
                ],
                [
                    'instrument_name' => 'ダイワ_DC外株インデックス',
                    'external_id' => 'money_forward:pension:ダイワ_DC外株インデックス',
                    'asset_class' => 'defined_contribution_pension',
                    'acquisition_cost' => '237667',
                    'valuation' => '582123',
                    'unrealized_gain' => '344456',
                    'currency' => 'JPY',
                ],
                [
                    'instrument_name' => '信託のチカラ日本の債券',
                    'external_id' => 'money_forward:pension:信託のチカラ日本の債券',
                    'asset_class' => 'defined_contribution_pension',
                    'acquisition_cost' => '237667',
                    'valuation' => '208607',
                    'unrealized_gain' => '-29060',
                    'currency' => 'JPY',
                ],
            ],
        ]];

        $this->actingAs($user)->post(route('imports.store'), [
            'source_name' => 'balance_snapshot',
            'account_id' => '',
            'csv_file' => UploadedFile::fake()->createWithContent(
                'money-forward-pension.json',
                json_encode($payload, JSON_THROW_ON_ERROR),
            ),
        ]);
        $import = Import::query()->sole();
        $row = $import->importRows()->sole();

        self::assertSame($pension->id, $row->resolved_account_id);
        self::assertSame('ready', $row->status);

        $this->actingAs($user)
            ->post(route('imports.commit', $import))
            ->assertSessionHasNoErrors();

        $snapshot = AccountSnapshot::query()->sole();
        self::assertSame($pension->id, $snapshot->account_id);
        self::assertSame('valuation', $snapshot->purpose);
        self::assertSame('1832820.00', (string) $snapshot->balance);
        self::assertSame(3, $snapshot->investmentPositions()->count());

        $stockPosition = $snapshot->investmentPositions()
            ->where('instrument_name', '信託のチカラ日本の株式')
            ->sole();
        self::assertSame('defined_contribution_pension', $stockPosition->asset_class);
        self::assertSame('475430.00', (string) $stockPosition->acquisition_cost);
        self::assertSame('1042090.00', (string) $stockPosition->valuation);
        self::assertSame('566660.00', (string) $stockPosition->unrealized_gain);
        self::assertSame('Money Forward 年金', $snapshot->metadata['source_account_name']);
    }

    public function test_reimport_does_not_duplicate_pension_positions_when_external_id_format_changes(): void
    {
        $user = User::factory()->create();
        $pension = Account::factory()->for($user)->create([
            'name' => 'JIS&T(確定拠出年金)',
            'type' => 'securities',
            'balance_role' => 'asset',
            'balance_method' => 'snapshot',
            'currency' => 'JPY',
            'initial_balance' => '0.00',
            'import_aliases' => ['Money Forward 年金'],
        ]);
        $legacyPayload = $this->payload();
        unset($legacyPayload['asset_history']);
        $legacyPayload['items'] = [[
            'source_account_name' => 'JIS&T(確定拠出年金)',
            'balance_kind' => 'valuation',
            'balance' => '12000',
            'currency' => 'JPY',
            'balance_date' => '2026-07-18',
            'positions' => [[
                'instrument_name' => 'Global_DC Index Fund',
                'external_id' => 'money_forward:JIS&T(確定拠出年金):Global_DC Index Fund',
                'asset_class' => 'security',
                'valuation' => '12000',
                'currency' => 'JPY',
            ]],
        ]];

        $this->actingAs($user)->post(route('imports.store'), [
            'source_name' => 'balance_snapshot',
            'account_id' => '',
            'csv_file' => UploadedFile::fake()->createWithContent(
                'legacy-pension.json',
                json_encode($legacyPayload, JSON_THROW_ON_ERROR),
            ),
        ]);
        $firstImport = Import::query()->sole();
        $this->actingAs($user)
            ->post(route('imports.commit', $firstImport))
            ->assertSessionHasNoErrors();

        $position = InvestmentPositionSnapshot::query()->sole();
        self::assertSame(
            hash('sha256', 'money_forward:pension:global_dc index fund|JPY'),
            $position->position_key,
        );

        $currentPayload = $legacyPayload;
        $currentPayload['items'][0]['source_account_name'] = 'Money Forward 年金';
        $currentPayload['items'][0]['positions'][0]['external_id'] =
            'money_forward:pension:Global_DC Index Fund';
        $currentPayload['items'][0]['positions'][0]['asset_class'] = 'defined_contribution_pension';

        $this->actingAs($user)->post(route('imports.store'), [
            'source_name' => 'balance_snapshot',
            'account_id' => '',
            'csv_file' => UploadedFile::fake()->createWithContent(
                'current-pension.json',
                json_encode($currentPayload, JSON_THROW_ON_ERROR),
            ),
        ]);
        $secondImport = Import::query()->latest('id')->firstOrFail();
        self::assertSame(1, $secondImport->duplicate_rows);

        $this->actingAs($user)
            ->post(route('imports.commit', $secondImport))
            ->assertSessionHasNoErrors();

        self::assertSame(1, AccountSnapshot::query()->where('account_id', $pension->id)->count());
        self::assertSame(1, InvestmentPositionSnapshot::query()->where('account_id', $pension->id)->count());
        self::assertSame(['skipped'], $secondImport->refresh()->importRows()->pluck('status')->all());
        self::assertSame(0, $secondImport->imported_rows);
    }

    public function test_user_can_explicitly_replace_a_different_balance_on_the_same_day(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create([
            'name' => 'THEO',
            'type' => 'securities',
            'balance_method' => 'snapshot',
            'currency' => 'JPY',
        ]);
        $firstPayload = $this->payload();
        $firstPayload['items'] = [$firstPayload['items'][0]];
        unset($firstPayload['items'][0]['positions']);

        $this->actingAs($user)->post(route('imports.store'), [
            'source_name' => 'balance_snapshot',
            'account_id' => '',
            'csv_file' => UploadedFile::fake()->createWithContent(
                'first-balance.json',
                json_encode($firstPayload, JSON_THROW_ON_ERROR),
            ),
        ]);
        $firstImport = Import::query()->sole();
        $this->actingAs($user)->post(route('imports.commit', $firstImport));
        $oldSnapshot = AccountSnapshot::query()->sole();

        $secondPayload = $this->payload();
        $secondPayload['items'] = [$secondPayload['items'][0]];
        $secondPayload['items'][0]['balance'] = '500000';
        $this->actingAs($user)->post(route('imports.store'), [
            'source_name' => 'balance_snapshot',
            'account_id' => '',
            'csv_file' => UploadedFile::fake()->createWithContent(
                'updated-balance.json',
                json_encode($secondPayload, JSON_THROW_ON_ERROR),
            ),
        ]);
        $secondImport = Import::query()->latest('id')->firstOrFail();
        $row = $secondImport->importRows()->sole();

        self::assertSame('error', $row->status);
        self::assertContains(
            '同じ日の残高がすでにあります。既存値を確認してから取り込んでください。',
            $row->validation_errors,
        );

        $this->actingAs($user)
            ->get(route('imports.show', $secondImport))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('rows.0.same_day_snapshot.id', $oldSnapshot->id)
                ->where('rows.0.same_day_snapshot.balance', '412345.67')
                ->where('rows.0.replace_account_snapshot_id', null));

        $this->actingAs(User::factory()->create())
            ->put(route('imports.rows.update-replacement', [$secondImport, $row]), [
                'replace_existing' => true,
            ])
            ->assertForbidden();

        $this->actingAs($user)
            ->put(route('imports.rows.update-replacement', [$secondImport, $row]), [
                'replace_existing' => true,
            ])
            ->assertRedirect(route('imports.preview', $secondImport));

        $row->refresh();
        self::assertSame($oldSnapshot->id, $row->replace_account_snapshot_id);
        self::assertSame('ready', $row->status);
        self::assertSame([], $row->validation_errors);

        $this->actingAs($user)
            ->post(route('imports.commit', $secondImport))
            ->assertSessionHasNoErrors()
            ->assertSessionMissing('error');

        $replacement = AccountSnapshot::query()->sole();
        self::assertNotSame($oldSnapshot->id, $replacement->id);
        self::assertSame($account->id, $replacement->account_id);
        self::assertSame($secondImport->id, $replacement->import_id);
        self::assertSame('500000.00', (string) $replacement->balance);
        self::assertSame(2, $replacement->investmentPositions()->count());
        self::assertSame('公式残高取込から同日残高を置き換え', $replacement->note);
        self::assertSame([
            'id' => $oldSnapshot->id,
            'import_id' => $oldSnapshot->import_id,
            'balance' => '412345.67',
            'captured_at' => $oldSnapshot->captured_at->toIso8601String(),
            'source_name' => 'Money Forward',
        ], $replacement->metadata['replaced_snapshot']);
        $this->assertDatabaseMissing('account_snapshots', ['id' => $oldSnapshot->id]);
    }

    public function test_reimport_marks_existing_snapshots_as_duplicates(): void
    {
        $user = User::factory()->create();
        Account::factory()->for($user)->create([
            'name' => 'THEO',
            'type' => 'securities',
            'balance_method' => 'snapshot',
            'initial_balance' => '0.00',
        ]);
        Account::factory()->for($user)->create([
            'name' => 'ソニー銀行 投資信託',
            'type' => 'securities',
            'balance_method' => 'snapshot',
            'initial_balance' => '0.00',
        ]);
        Account::factory()->for($user)->create([
            'name' => 'dカード',
            'type' => 'credit_card',
            'balance_role' => 'liability',
            'initial_balance' => '0.00',
        ]);

        $this->actingAs($user)->post(route('imports.store'), $this->uploadPayload('first.json'));
        $first = Import::query()->sole();
        $this->actingAs($user)->post(route('imports.commit', $first));

        $this->actingAs($user)->post(route('imports.store'), $this->uploadPayload('second.json'));
        $second = Import::query()->latest('id')->firstOrFail();

        self::assertSame(3, $second->duplicate_rows);
        self::assertTrue($second->importRows()->get()->every->is_duplicate_candidate);

        $this->actingAs($user)
            ->post(route('imports.commit', $second))
            ->assertRedirect(route('imports.show', $second))
            ->assertSessionHasNoErrors()
            ->assertSessionMissing('error');

        self::assertSame(3, AccountSnapshot::query()->count());
        self::assertSame(2, InvestmentPositionSnapshot::query()->count());
        self::assertSame('imported', $second->refresh()->status);
        self::assertSame(['skipped', 'skipped', 'skipped'], $second->importRows()->pluck('status')->all());
        self::assertSame(0, $second->imported_rows);
        self::assertSame(3, $second->skipped_rows);
    }

    public function test_duplicate_snapshot_can_be_enriched_with_new_position_details(): void
    {
        $user = User::factory()->create();
        Account::factory()->for($user)->create([
            'name' => 'THEO',
            'type' => 'securities',
            'balance_method' => 'snapshot',
            'initial_balance' => '0.00',
        ]);
        $firstPayload = $this->payload();
        $firstPayload['items'] = [$firstPayload['items'][0]];
        unset($firstPayload['items'][0]['positions']);

        $this->actingAs($user)->post(route('imports.store'), [
            'source_name' => 'balance_snapshot',
            'account_id' => '',
            'csv_file' => UploadedFile::fake()->createWithContent(
                'without-positions.json',
                json_encode($firstPayload, JSON_THROW_ON_ERROR),
            ),
        ]);
        $first = Import::query()->sole();
        $this->actingAs($user)->post(route('imports.commit', $first));

        self::assertSame(1, AccountSnapshot::query()->count());
        self::assertSame(0, InvestmentPositionSnapshot::query()->count());

        $secondPayload = $this->payload();
        $secondPayload['items'] = [$secondPayload['items'][0]];
        $this->actingAs($user)->post(route('imports.store'), [
            'source_name' => 'balance_snapshot',
            'account_id' => '',
            'csv_file' => UploadedFile::fake()->createWithContent(
                'with-positions.json',
                json_encode($secondPayload, JSON_THROW_ON_ERROR),
            ),
        ]);
        $second = Import::query()->latest('id')->firstOrFail();

        self::assertSame(1, $second->duplicate_rows);

        $this->actingAs($user)
            ->post(route('imports.commit', $second))
            ->assertSessionHasNoErrors();

        $second->refresh();
        self::assertSame('imported', $second->status);
        self::assertSame(['imported'], $second->importRows()->pluck('status')->all());
        self::assertSame(1, AccountSnapshot::query()->count());
        self::assertSame(2, InvestmentPositionSnapshot::query()->count());
        self::assertSame(1, $second->imported_rows);
        self::assertSame(0, $second->skipped_rows);

        $this->actingAs($user)
            ->delete(route('imports.destroy', $second))
            ->assertRedirect(route('imports.index'));

        self::assertSame(1, AccountSnapshot::query()->count());
        self::assertSame(0, InvestmentPositionSnapshot::query()->count());
    }

    public function test_user_can_manually_resolve_an_unmatched_balance_account(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create([
            'name' => 'THEO',
            'type' => 'securities',
            'balance_method' => 'snapshot',
        ]);
        $payload = $this->payload();
        $payload['items'] = [$payload['items'][0]];
        $payload['items'][0]['source_account_name'] = 'THEO+ docomo';

        $this->actingAs($user)->post(route('imports.store'), [
            'source_name' => 'balance_snapshot',
            'account_id' => '',
            'csv_file' => UploadedFile::fake()->createWithContent(
                'balance.json',
                json_encode($payload, JSON_THROW_ON_ERROR),
            ),
        ]);
        $import = Import::query()->sole();
        $row = $import->importRows()->sole();
        self::assertSame('error', $row->status);

        $this->actingAs($user)
            ->put(route('imports.rows.update-account', [$import, $row]), [
                'resolved_account_id' => (string) $account->id,
            ])
            ->assertRedirect(route('imports.preview', $import));

        $row->refresh();
        self::assertSame($account->id, $row->manual_resolved_account_id);
        self::assertSame($account->id, $row->resolved_account_id);
        self::assertSame('ready', $row->status);
    }

    public function test_user_can_remember_a_balance_source_account_mapping(): void
    {
        $user = User::factory()->create();
        $pension = Account::factory()->for($user)->create([
            'name' => '企業型確定拠出年金',
            'type' => 'securities',
            'balance_method' => 'snapshot',
            'import_aliases' => [],
        ]);
        $payload = $this->payload();
        $payload['items'] = [[
            'source_account_name' => 'Money Forward 年金',
            'balance_kind' => 'valuation',
            'balance' => '1832820',
            'currency' => 'JPY',
            'balance_date' => '2026-07-18',
        ]];

        $this->actingAs($user)->post(route('imports.store'), [
            'source_name' => 'balance_snapshot',
            'account_id' => '',
            'csv_file' => UploadedFile::fake()->createWithContent(
                'first-pension.json',
                json_encode($payload, JSON_THROW_ON_ERROR),
            ),
        ]);
        $firstImport = Import::query()->sole();
        $firstRow = $firstImport->importRows()->sole();
        self::assertNull($firstRow->resolved_account_id);

        $this->actingAs($user)
            ->put(route('imports.rows.update-account', [$firstImport, $firstRow]), [
                'resolved_account_id' => (string) $pension->id,
                'remember_mapping' => true,
            ])
            ->assertSessionHasNoErrors();

        self::assertSame(['Money Forward 年金'], $pension->refresh()->import_aliases);
        self::assertSame($pension->id, $firstRow->refresh()->resolved_account_id);

        $payload['items'][0]['balance_date'] = '2026-07-19';
        $this->actingAs($user)->post(route('imports.store'), [
            'source_name' => 'balance_snapshot',
            'account_id' => '',
            'csv_file' => UploadedFile::fake()->createWithContent(
                'second-pension.json',
                json_encode($payload, JSON_THROW_ON_ERROR),
            ),
        ]);
        $secondRow = Import::query()->latest('id')->firstOrFail()->importRows()->sole();

        self::assertNull($secondRow->manual_resolved_account_id);
        self::assertSame($pension->id, $secondRow->resolved_account_id);
        self::assertSame('ready', $secondRow->status);
    }

    public function test_money_forward_card_name_is_resolved_after_width_normalization(): void
    {
        $user = User::factory()->create();
        $card = Account::factory()->for($user)->create([
            'name' => '日専連カード（ニッセンレンエスコート）',
            'type' => 'credit_card',
            'balance_role' => 'liability',
            'balance_method' => 'ledger',
            'currency' => 'JPY',
        ]);
        $payload = $this->payload();
        $payload['items'] = [[
            'source_account_name' => '日専連カード(ニッセンレンエスコート)',
            'balance_kind' => 'card_outstanding',
            'balance' => '1234',
            'currency' => 'JPY',
            'balance_date' => '2026-07-18',
        ]];

        $this->actingAs($user)->post(route('imports.store'), [
            'source_name' => 'balance_snapshot',
            'account_id' => '',
            'csv_file' => UploadedFile::fake()->createWithContent(
                'money-forward-balances.json',
                json_encode($payload, JSON_THROW_ON_ERROR),
            ),
        ]);

        $row = Import::query()->sole()->importRows()->sole();

        self::assertSame($card->id, $row->resolved_account_id);
        self::assertSame('-1234.00', (string) $row->amount);
        self::assertSame('ready', $row->status);
    }

    public function test_user_cannot_map_balance_to_another_users_account(): void
    {
        $user = User::factory()->create();
        $otherAccount = Account::factory()->for(User::factory()->create())->create([
            'type' => 'securities',
            'balance_method' => 'snapshot',
        ]);
        $payload = $this->payload();
        $payload['items'] = [$payload['items'][0]];
        $payload['items'][0]['source_account_name'] = 'unmatched';

        $this->actingAs($user)->post(route('imports.store'), [
            'source_name' => 'balance_snapshot',
            'account_id' => '',
            'csv_file' => UploadedFile::fake()->createWithContent(
                'balance.json',
                json_encode($payload, JSON_THROW_ON_ERROR),
            ),
        ]);
        $import = Import::query()->sole();
        $row = $import->importRows()->sole();

        $this->actingAs($user)
            ->put(route('imports.rows.update-account', [$import, $row]), [
                'resolved_account_id' => (string) $otherAccount->id,
            ])
            ->assertSessionHasErrors("resolved_account_id.{$row->id}");

        self::assertNull($row->refresh()->manual_resolved_account_id);
    }

    public function test_deleting_an_import_removes_its_balance_snapshots(): void
    {
        $user = User::factory()->create();
        Account::factory()->for($user)->create([
            'name' => 'THEO',
            'type' => 'securities',
            'balance_method' => 'snapshot',
        ]);
        Account::factory()->for($user)->create([
            'name' => 'ソニー銀行 投資信託',
            'type' => 'securities',
            'balance_method' => 'snapshot',
        ]);
        Account::factory()->for($user)->create([
            'name' => 'dカード',
            'type' => 'credit_card',
            'balance_role' => 'liability',
        ]);

        $this->actingAs($user)->post(route('imports.store'), $this->uploadPayload());
        $import = Import::query()->sole();
        $this->actingAs($user)->post(route('imports.commit', $import));
        self::assertSame(3, AccountSnapshot::query()->count());

        $this->actingAs($user)
            ->delete(route('imports.destroy', $import))
            ->assertRedirect(route('imports.index'));

        $this->assertDatabaseCount('account_snapshots', 0);
        $this->assertDatabaseCount('investment_position_snapshots', 0);
        $this->assertDatabaseCount('asset_history_snapshots', 0);
        $this->assertDatabaseMissing('imports', ['id' => $import->id]);
    }

    /**
     * @return array<string, mixed>
     */
    private function uploadPayload(string $filename = 'balances.json'): array
    {
        return [
            'source_name' => 'balance_snapshot',
            'account_id' => '',
            'csv_file' => UploadedFile::fake()->createWithContent(
                $filename,
                json_encode($this->payload(), JSON_THROW_ON_ERROR),
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(): array
    {
        return [
            'format' => 'nkkakeist-balance-snapshot',
            'version' => 1,
            'source' => 'money_forward',
            'captured_at' => '2026-07-18T21:00:00+09:00',
            'diagnostics' => [
                'exporter_version' => 2,
                'portfolio_summary_table' => true,
                'investment_tables' => 2,
                'deposit_table' => true,
                'pension_table' => true,
                'liability_tables' => 1,
            ],
            'asset_history' => [
                'captured_on' => '2026-07-18',
                'total_assets' => '22667260',
                'currency' => 'JPY',
                'breakdown' => [
                    '預金・現金' => '5300726',
                    '投資信託' => '15525499',
                    '年金' => '1832820',
                    'ポイント' => '8215',
                ],
            ],
            'items' => [
                [
                    'source_account_name' => 'THEO',
                    'balance_kind' => 'valuation',
                    'balance' => '412345.67',
                    'currency' => 'JPY',
                    'source_updated_at' => '2026-07-18T20:30:00+09:00',
                    'positions' => [
                        [
                            'instrument_name' => 'グロース株式',
                            'external_id' => 'money_forward:THEO:グロース株式',
                            'asset_class' => 'investment_fund',
                            'quantity' => '123.45678901',
                            'average_acquisition_price' => '9876.5',
                            'unit_price' => '10001',
                            'acquisition_cost' => '206200',
                            'valuation' => '205000',
                            'unrealized_gain' => '-1200',
                            'currency' => 'JPY',
                        ],
                        [
                            'instrument_name' => 'インカム債券',
                            'external_id' => 'money_forward:THEO:インカム債券',
                            'asset_class' => 'investment_fund',
                            'quantity' => '45.5',
                            'average_acquisition_price' => '4500',
                            'unit_price' => '4557.047692',
                            'valuation' => '207345.67',
                            'unrealized_gain' => '2345.67',
                            'currency' => 'JPY',
                        ],
                    ],
                ],
                [
                    'source_account_name' => 'ソニー銀行 投資信託',
                    'balance_kind' => 'valuation',
                    'balance' => '123456.78',
                    'currency' => 'JPY',
                    'source_updated_at' => '2026-07-18T20:30:00+09:00',
                ],
                [
                    'source_account_name' => 'dカード',
                    'balance_kind' => 'card_outstanding',
                    'balance' => '65940',
                    'currency' => 'JPY',
                    'balance_date' => '2026-07-18',
                    'next_payment_amount' => '42000',
                    'next_payment_date' => '2026-08-10',
                ],
            ],
        ];
    }
}
