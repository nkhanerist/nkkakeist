<?php

namespace App\Actions\Imports;

use App\Models\AssetHistorySnapshot;
use App\Models\Import;
use App\Models\ImportRow;
use App\Services\Imports\AssetHistoryImportValidationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CommitAssetHistoryImportAction
{
    public function __construct(
        private readonly AssetHistoryImportValidationService $validationService,
    ) {}

    public function handle(Import $import): Import
    {
        if ($import->status === 'imported') {
            throw ValidationException::withMessages(['import' => 'すでに取込済みです。']);
        }

        if ($import->status !== 'validated') {
            throw ValidationException::withMessages(['import' => '資産推移を反映できるのはプレビュー完了済みの import のみです。']);
        }

        $import = $this->validationService->handle($import);

        if ($import->importRows->contains(fn (ImportRow $row): bool => $row->status === 'error')) {
            throw ValidationException::withMessages(['import' => '資産推移に不正な行があります。']);
        }

        DB::transaction(function () use ($import): void {
            $importedRows = 0;
            $skippedRows = 0;

            foreach ($import->importRows as $importRow) {
                if ($importRow->is_duplicate_candidate || $importRow->status !== 'ready') {
                    $importRow->update(['status' => 'skipped']);
                    $skippedRows++;

                    continue;
                }

                AssetHistorySnapshot::query()->create([
                    'user_id' => $import->user_id,
                    'import_id' => $import->id,
                    'import_row_id' => $importRow->id,
                    'captured_on' => $importRow->transaction_date?->toDateString(),
                    'total_assets' => $importRow->amount,
                    'currency' => $importRow->raw_payload['currency'] ?? 'JPY',
                    'source_name' => 'money_forward',
                    'duplicate_hash' => $importRow->duplicate_hash,
                    'breakdown' => $importRow->raw_payload['breakdown'] ?? [],
                ]);
                $importRow->update(['status' => 'imported']);
                $importedRows++;
            }

            $import->update([
                'status' => 'imported',
                'imported_rows' => $importedRows,
                'skipped_rows' => $skippedRows,
                'imported_at' => now(),
                'error_message' => null,
            ]);
        });

        return $import->fresh(['account', 'importRows', 'assetHistorySnapshots']);
    }
}
