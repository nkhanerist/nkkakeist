<?php

namespace App\Services\Imports;

use App\Models\AssetHistorySnapshot;
use App\Models\Import;
use Illuminate\Support\Facades\DB;

class AssetHistoryImportValidationService
{
    public function handle(Import $import): Import
    {
        $import->loadMissing('importRows');
        $duplicateRows = 0;

        DB::transaction(function () use ($import, &$duplicateRows): void {
            $seenHashes = [];

            foreach ($import->importRows as $importRow) {
                $date = $importRow->transaction_date?->toDateString();
                $amount = $importRow->amount;
                $errors = [];

                if ($date === null) {
                    $errors[] = '資産履歴の日付を解釈できません。';
                }

                if ($amount === null || str_starts_with((string) $amount, '-')) {
                    $errors[] = '総資産額が不正です。';
                }

                $duplicateHash = $errors === []
                    ? hash('sha256', implode('|', [$import->user_id, 'money_forward', $date, (string) $amount]))
                    : null;
                $isDuplicate = $duplicateHash !== null && (
                    isset($seenHashes[$duplicateHash])
                    || AssetHistorySnapshot::query()
                        ->where('user_id', $import->user_id)
                        ->whereDate('captured_on', $date)
                        ->where('source_name', 'money_forward')
                        ->exists()
                );

                if ($duplicateHash !== null) {
                    $seenHashes[$duplicateHash] = true;
                }

                if ($isDuplicate) {
                    $duplicateRows++;
                }

                $importRow->update([
                    'resolved_account_id' => null,
                    'resolved_transfer_account_id' => null,
                    'resolved_category_id' => null,
                    'resolved_subcategory_id' => null,
                    'resolved_is_calculation_target' => false,
                    'resolved_affects_account_balance' => false,
                    'duplicate_hash' => $duplicateHash,
                    'is_duplicate_candidate' => $isDuplicate,
                    'validation_errors' => $errors,
                    'status' => $errors === [] ? 'ready' : 'error',
                ]);
            }

            $import->update([
                'status' => 'validated',
                'duplicate_rows' => $duplicateRows,
                'error_message' => null,
            ]);
        });

        return $import->fresh(['account', 'importRows']);
    }
}
