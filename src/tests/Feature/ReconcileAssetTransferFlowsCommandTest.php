<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Import;
use App\Models\ImportRow;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReconcileAssetTransferFlowsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_is_read_only_without_apply(): void
    {
        $data = $this->seedAssetTransfers();

        $this->artisan('accounts:reconcile-asset-flows', [
            '--user' => $data['user']->id,
        ])
            ->expectsOutputToContain('read-only診断です。')
            ->assertSuccessful();

        $this->assertDatabaseHas('transactions', [
            'id' => $data['kyash_mirror']->id,
            'deleted_at' => null,
        ]);
        $this->assertDatabaseHas('transactions', [
            'id' => $data['theo_card']->id,
            'transfer_account_id' => $data['theo']->id,
        ]);
    }

    public function test_command_applies_known_asset_transfer_corrections(): void
    {
        $data = $this->seedAssetTransfers();

        $this->artisan('accounts:reconcile-asset-flows', [
            '--user' => $data['user']->id,
            '--apply' => true,
        ])
            ->expectsOutputToContain('資産移動の補正を適用しました。')
            ->assertSuccessful();

        $this->assertSoftDeleted('transactions', [
            'id' => $data['kyash_mirror']->id,
        ]);
        $this->assertDatabaseHas('transactions', [
            'id' => $data['kyash_canonical']->id,
            'account_id' => $data['dcard']->id,
            'transfer_account_id' => $data['kyash']->id,
            'deleted_at' => null,
        ]);
        $this->assertDatabaseHas('transactions', [
            'id' => $data['unmatched_kyash_transfer']->id,
            'deleted_at' => null,
        ]);
        $this->assertDatabaseHas('transactions', [
            'id' => $data['theo_card']->id,
            'account_id' => $data['dcard']->id,
            'transfer_account_id' => $data['dpay']->id,
        ]);

        $this->assertDatabaseHas('import_rows', [
            'id' => $data['kyash_mirror_row']->id,
            'status' => 'skipped',
            'is_duplicate_candidate' => 1,
        ]);
        $this->assertDatabaseHas('import_rows', [
            'id' => $data['theo_card_row']->id,
            'resolved_transfer_account_id' => $data['dpay']->id,
        ]);
        $this->assertDatabaseHas('imports', [
            'id' => $data['import']->id,
            'imported_rows' => 3,
            'skipped_rows' => 1,
            'duplicate_rows' => 1,
        ]);

        self::assertNotContains(
            'THEO積立/SMBC日興証券',
            $data['dcard']->fresh()->import_aliases ?? [],
        );
        self::assertContains(
            'THEO積立/SMBC日興証券',
            $data['dpay']->fresh()->import_aliases ?? [],
        );

        $this->artisan('accounts:reconcile-asset-flows', [
            '--user' => $data['user']->id,
            '--apply' => true,
        ])->assertSuccessful();

        self::assertSame(1, Transaction::withTrashed()->whereKey($data['kyash_mirror']->id)->count());
    }

    public function test_command_requires_a_valid_user_id(): void
    {
        $this->artisan('accounts:reconcile-asset-flows')
            ->expectsOutputToContain('--user は正の整数で指定してください。')
            ->assertExitCode(2);
    }

    /**
     * @return array<string, mixed>
     */
    private function seedAssetTransfers(): array
    {
        $user = User::factory()->create();
        $dCard = Account::factory()->for($user)->create([
            'name' => 'dカード',
            'type' => 'credit_card',
            'import_aliases' => ['MasterCard(8658)', 'THEO積立/SMBC日興証券'],
        ]);
        $dPay = Account::factory()->for($user)->create([
            'name' => 'd払い',
            'type' => 'e_money',
            'import_aliases' => [],
        ]);
        $kyash = Account::factory()->for($user)->create([
            'name' => 'kyash',
            'type' => 'other',
        ]);
        $theo = Account::factory()->for($user)->create([
            'name' => 'THEO',
            'type' => 'securities',
            'balance_method' => 'snapshot',
        ]);

        $import = Import::query()->create([
            'user_id' => $user->id,
            'source_name' => 'money_forward',
            'original_filename' => 'asset-flows.csv',
            'storage_path' => 'imports/asset-flows.csv',
            'status' => 'imported',
            'total_rows' => 4,
            'imported_rows' => 4,
            'skipped_rows' => 0,
            'duplicate_rows' => 0,
        ]);

        $kyashMirrorRow = $this->importRow($import, 1, $kyash, $dCard, 'カード MasterCard(8658)', '3000.00');
        $kyashCanonicalRow = $this->importRow($import, 2, $dCard, $kyash, 'Kyash', '3000.00');
        $theoCardRow = $this->importRow($import, 3, $dCard, $theo, 'THEO積立/SMBC日興証券', '30000.00');
        $theoDpayRow = $this->importRow($import, 4, $dPay, $theo, 'THEO+docomo(dカード積立)', '30000.00');

        $kyashMirror = $this->transaction($import, $kyashMirrorRow, $kyash, $dCard);
        $kyashCanonical = $this->transaction($import, $kyashCanonicalRow, $dCard, $kyash);
        $theoCard = $this->transaction($import, $theoCardRow, $dCard, $theo);
        $this->transaction($import, $theoDpayRow, $dPay, $theo);

        $unmatchedKyashTransfer = Transaction::factory()->transfer($kyash, $dCard)->create([
            'user_id' => $user->id,
            'transaction_date' => '2026-02-01',
            'amount' => '9000.00',
            'merchant_name' => 'カード MasterCard(8658)',
            'currency' => 'JPY',
        ]);

        return [
            'user' => $user,
            'dcard' => $dCard,
            'dpay' => $dPay,
            'kyash' => $kyash,
            'theo' => $theo,
            'import' => $import,
            'kyash_mirror_row' => $kyashMirrorRow,
            'theo_card_row' => $theoCardRow,
            'kyash_mirror' => $kyashMirror,
            'kyash_canonical' => $kyashCanonical,
            'theo_card' => $theoCard,
            'unmatched_kyash_transfer' => $unmatchedKyashTransfer,
        ];
    }

    private function importRow(
        Import $import,
        int $rowNumber,
        Account $source,
        Account $destination,
        string $merchant,
        string $amount,
    ): ImportRow {
        return ImportRow::query()->create([
            'import_id' => $import->id,
            'row_number' => $rowNumber,
            'raw_payload' => ['内容' => $merchant],
            'transaction_date' => '2026-01-01',
            'amount' => $amount,
            'merchant_name' => $merchant,
            'account_name' => $source->name,
            'detected_type' => 'transfer',
            'resolved_account_id' => $source->id,
            'resolved_transfer_account_id' => $destination->id,
            'is_calculation_target' => false,
            'affects_account_balance' => true,
            'is_duplicate_candidate' => false,
            'status' => 'imported',
        ]);
    }

    private function transaction(
        Import $import,
        ImportRow $importRow,
        Account $source,
        Account $destination,
    ): Transaction {
        return Transaction::factory()->transfer($source, $destination)->create([
            'user_id' => $import->user_id,
            'transaction_date' => $importRow->transaction_date,
            'amount' => $importRow->amount,
            'merchant_name' => $importRow->merchant_name,
            'currency' => 'JPY',
            'import_id' => $import->id,
            'import_row_id' => $importRow->id,
        ]);
    }
}
