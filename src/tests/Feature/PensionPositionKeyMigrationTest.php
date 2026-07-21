<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountSnapshot;
use App\Models\Import;
use App\Models\InvestmentPositionSnapshot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PensionPositionKeyMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_canonicalizes_legacy_pension_position_keys(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create([
            'name' => 'JIS&T(確定拠出年金)',
            'type' => 'securities',
            'currency' => 'JPY',
        ]);
        $import = Import::query()->create([
            'user_id' => $user->id,
            'source_name' => 'balance_snapshot',
            'original_filename' => 'legacy-pension.json',
            'storage_path' => 'imports/legacy-pension.json',
            'status' => 'imported',
            'total_rows' => 1,
            'imported_rows' => 1,
        ]);
        $snapshot = AccountSnapshot::query()->create([
            'user_id' => $user->id,
            'account_id' => $account->id,
            'import_id' => $import->id,
            'captured_at' => '2026-07-18 23:59:59',
            'purpose' => 'valuation',
            'balance' => '12000.00',
            'source_name' => 'Money Forward',
        ]);
        $legacyKey = hash(
            'sha256',
            'money_forward:pension:Global_DC Index Fund|JPY',
        );
        $canonicalKey = hash(
            'sha256',
            'money_forward:pension:global_dc index fund|JPY',
        );
        $position = InvestmentPositionSnapshot::query()->create([
            'user_id' => $user->id,
            'account_id' => $account->id,
            'account_snapshot_id' => $snapshot->id,
            'import_id' => $import->id,
            'captured_at' => $snapshot->captured_at,
            'position_key' => $legacyKey,
            'instrument_name' => 'Global_DC Index Fund',
            'external_id' => 'money_forward:pension:Global_DC Index Fund',
            'asset_class' => 'defined_contribution_pension',
            'valuation' => '12000.00',
            'currency' => 'JPY',
            'source_name' => 'Money Forward',
        ]);

        $migration = require database_path(
            'migrations/2026_07_21_100000_canonicalize_pension_position_keys.php',
        );
        $migration->up();

        self::assertSame($canonicalKey, $position->refresh()->position_key);
    }
}
