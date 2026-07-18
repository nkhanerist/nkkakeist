<?php

namespace App\Actions\Imports;

use App\Models\Account;
use App\Models\AccountSnapshot;
use App\Models\Import;
use App\Models\ImportRow;
use App\Services\Imports\BalanceSnapshotImportValidationService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CommitBalanceSnapshotImportAction
{
    public function __construct(
        private readonly BalanceSnapshotImportValidationService $validationService,
    ) {}

    public function handle(Import $import): Import
    {
        if ($import->status === 'imported') {
            throw ValidationException::withMessages([
                'import' => 'すでに取込済みです。',
            ]);
        }

        if ($import->status !== 'validated') {
            throw ValidationException::withMessages([
                'import' => '残高を反映できるのはプレビュー完了済みの import のみです。',
            ]);
        }

        $import = $this->validationService->handle($import);
        $import->loadMissing('user', 'importRows.resolvedAccount');

        if ($import->importRows->contains(fn (ImportRow $row): bool => $row->status === 'error')) {
            throw ValidationException::withMessages([
                'import' => '未解決の残高項目があります。すべて解決してから確定してください。',
            ]);
        }

        DB::transaction(function () use ($import): void {
            $importedRows = 0;
            $skippedRows = 0;
            $duplicateRows = 0;

            foreach ($import->importRows as $importRow) {
                if ($importRow->is_duplicate_candidate) {
                    $importRow->update(['status' => 'skipped']);
                    $skippedRows++;
                    $duplicateRows++;

                    continue;
                }

                if ($importRow->status !== 'ready') {
                    $importRow->update(['status' => 'skipped']);
                    $skippedRows++;

                    continue;
                }

                $this->createSnapshot($import, $importRow);
                $importRow->update(['status' => 'imported']);
                $importedRows++;
            }

            $import->update([
                'status' => 'imported',
                'imported_rows' => $importedRows,
                'skipped_rows' => $skippedRows,
                'duplicate_rows' => $duplicateRows,
                'imported_at' => now(),
                'error_message' => null,
            ]);
        });

        return $import->fresh(['account', 'importRows.resolvedAccount', 'accountSnapshots']);
    }

    private function createSnapshot(Import $import, ImportRow $importRow): AccountSnapshot
    {
        $account = $importRow->resolvedAccount;

        if (! $account instanceof Account || $account->user_id !== $import->user_id) {
            throw ValidationException::withMessages([
                'import' => '取込先口座を確定できないため残高を反映できません。',
            ]);
        }

        $rawPayload = $importRow->raw_payload ?? [];
        $balanceKind = is_string($rawPayload['balance_kind'] ?? null)
            ? $rawPayload['balance_kind']
            : null;
        $purpose = $balanceKind === 'valuation' ? 'valuation' : 'official_balance';
        $balanceDate = $importRow->transaction_date?->toDateString();

        if ($balanceDate === null || $importRow->amount === null) {
            throw ValidationException::withMessages([
                'import' => '残高日または金額がないため反映できません。',
            ]);
        }

        $capturedAt = CarbonImmutable::parse($balanceDate, config('app.timezone'))->endOfDay();
        $source = (string) ($import->source_metadata['source'] ?? 'balance_snapshot');

        return $account->snapshots()->create([
            'user_id' => $import->user_id,
            'import_id' => $import->id,
            'import_row_id' => $importRow->id,
            'captured_at' => $capturedAt,
            'purpose' => $purpose,
            'duplicate_hash' => $importRow->duplicate_hash,
            'balance' => $importRow->amount,
            'source_name' => $this->sourceLabel($source),
            'note' => '公式残高取込から記録',
            'metadata' => [
                'balance_kind' => $balanceKind,
                'source' => $source,
                'source_account_name' => $importRow->account_name,
                'captured_at' => $import->source_metadata['captured_at'] ?? null,
                'source_updated_at' => $rawPayload['source_updated_at'] ?? null,
                'next_payment_amount' => $rawPayload['next_payment_amount'] ?? null,
                'next_payment_date' => $rawPayload['next_payment_date'] ?? null,
                'external_id' => $rawPayload['external_id'] ?? null,
            ],
        ]);
    }

    private function sourceLabel(string $source): string
    {
        return match ($source) {
            'money_forward' => 'Money Forward',
            'theo' => 'THEO',
            'sony_bank' => 'ソニー銀行',
            default => $source,
        };
    }
}
