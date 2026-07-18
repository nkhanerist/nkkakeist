<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountSnapshot;
use App\Models\Import;
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
            ->assertSessionHasNoErrors();

        self::assertSame(3, AccountSnapshot::query()->count());
        self::assertSame(0, $second->refresh()->imported_rows);
        self::assertSame(3, $second->skipped_rows);
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
            'items' => [
                [
                    'source_account_name' => 'THEO',
                    'balance_kind' => 'valuation',
                    'balance' => '412345.67',
                    'currency' => 'JPY',
                    'source_updated_at' => '2026-07-18T20:30:00+09:00',
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
