<?php

namespace App\Actions\Imports;

use App\Models\Account;
use App\Models\AccountSnapshot;
use App\Models\AssetHistorySnapshot;
use App\Models\Import;
use App\Models\ImportRow;
use App\Services\Imports\BalanceSnapshotConflictService;
use App\Services\Imports\BalanceSnapshotImportValidationService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CommitBalanceSnapshotImportAction
{
    public function __construct(
        private readonly BalanceSnapshotImportValidationService $validationService,
        private readonly BalanceSnapshotConflictService $conflictService,
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
                    $duplicateRows++;

                    if ($this->enrichDuplicateSnapshotPositions($import, $importRow)) {
                        $importRow->update(['status' => 'imported']);
                        $importedRows++;

                        continue;
                    }

                    $importRow->update(['status' => 'skipped']);
                    $skippedRows++;

                    continue;
                }

                if ($importRow->status !== 'ready') {
                    $importRow->update(['status' => 'skipped']);
                    $skippedRows++;

                    continue;
                }

                if ($importRow->replace_account_snapshot_id !== null) {
                    $this->replaceSnapshot($import, $importRow);
                } else {
                    $this->createSnapshot($import, $importRow);
                }
                $importRow->update(['status' => 'imported']);
                $importedRows++;
            }

            $this->commitAssetHistorySnapshot($import);

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

    private function replaceSnapshot(Import $import, ImportRow $importRow): AccountSnapshot
    {
        $snapshot = $this->conflictService->find($import, $importRow);

        if (
            ! $snapshot instanceof AccountSnapshot
            || $snapshot->id !== $importRow->replace_account_snapshot_id
        ) {
            throw ValidationException::withMessages([
                'import' => '置き換える同日の既存残高を再確認できませんでした。プレビューを更新してください。',
            ]);
        }

        $snapshot->delete();

        return $this->createSnapshot($import, $importRow);
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

        $snapshot = $account->snapshots()->create([
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
                'source_details' => $rawPayload['source_details'] ?? null,
            ],
        ]);

        if ($purpose === 'valuation') {
            $this->createPositionSnapshots($import, $snapshot, $rawPayload, $source);
        }

        return $snapshot;
    }

    /** @param array<string, mixed> $rawPayload */
    private function enrichDuplicateSnapshotPositions(Import $import, ImportRow $importRow): bool
    {
        $rawPayload = $importRow->raw_payload ?? [];
        $positions = $rawPayload['positions'] ?? [];
        $account = $importRow->resolvedAccount;

        if (! is_array($positions) || $positions === [] || ! $account instanceof Account) {
            return false;
        }

        $snapshot = $account->snapshots()
            ->where('user_id', $import->user_id)
            ->where('duplicate_hash', $importRow->duplicate_hash)
            ->first();

        if (! $snapshot instanceof AccountSnapshot) {
            return false;
        }

        $source = (string) ($import->source_metadata['source'] ?? 'balance_snapshot');

        return $this->createPositionSnapshots($import, $snapshot, $rawPayload, $source) > 0;
    }

    /**
     * @param  array<string, mixed>  $rawPayload
     */
    private function createPositionSnapshots(
        Import $import,
        AccountSnapshot $snapshot,
        array $rawPayload,
        string $source,
    ): int {
        $positions = $rawPayload['positions'] ?? [];

        if (! is_array($positions)) {
            return 0;
        }

        $created = 0;

        foreach ($positions as $position) {
            if (! is_array($position)) {
                continue;
            }

            $instrumentName = (string) ($position['instrument_name'] ?? '');
            $instrumentCode = is_string($position['instrument_code'] ?? null)
                ? $position['instrument_code']
                : null;
            $externalId = is_string($position['external_id'] ?? null)
                ? $position['external_id']
                : null;
            $currency = (string) ($position['currency'] ?? 'JPY');
            $identity = $externalId ?? $instrumentCode ?? $instrumentName;
            $positionKey = hash('sha256', implode('|', [$identity, $currency]));

            if ($snapshot->investmentPositions()->where('position_key', $positionKey)->exists()) {
                continue;
            }

            $snapshot->investmentPositions()->create([
                'user_id' => $snapshot->user_id,
                'account_id' => $snapshot->account_id,
                'import_id' => $import->id,
                'captured_at' => $snapshot->captured_at,
                'position_key' => $positionKey,
                'instrument_name' => $instrumentName,
                'instrument_code' => $instrumentCode,
                'external_id' => $externalId,
                'asset_class' => $position['asset_class'] ?? null,
                'quantity' => $position['quantity'] ?? null,
                'average_acquisition_price' => $position['average_acquisition_price'] ?? null,
                'unit_price' => $position['unit_price'] ?? null,
                'valuation' => $position['valuation'],
                'unrealized_gain' => $position['unrealized_gain'] ?? null,
                'currency' => $currency,
                'source_name' => $this->sourceLabel($source),
                'metadata' => [
                    'source' => $source,
                    'source_account_name' => $rawPayload['source_account_name'] ?? null,
                    'acquisition_cost' => $position['acquisition_cost'] ?? null,
                ],
            ]);
            $created++;
        }

        return $created;
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

    private function commitAssetHistorySnapshot(Import $import): void
    {
        $history = $import->source_metadata['asset_history'] ?? null;

        if (! is_array($history) || ($import->source_metadata['source'] ?? null) !== 'money_forward') {
            return;
        }

        $capturedOn = $history['captured_on'] ?? null;
        $totalAssets = $history['total_assets'] ?? null;

        if (! is_string($capturedOn) || ! is_string($totalAssets)) {
            return;
        }

        $values = [
            'import_id' => $import->id,
            'import_row_id' => null,
            'total_assets' => $totalAssets,
            'currency' => $history['currency'] ?? 'JPY',
            'duplicate_hash' => hash('sha256', implode('|', [$import->user_id, 'money_forward', $capturedOn, $totalAssets])),
            'breakdown' => $history['breakdown'] ?? [],
        ];
        $snapshot = AssetHistorySnapshot::query()
            ->where('user_id', $import->user_id)
            ->whereDate('captured_on', $capturedOn)
            ->where('source_name', 'money_forward')
            ->first();

        if ($snapshot instanceof AssetHistorySnapshot) {
            $snapshot->update($values);

            return;
        }

        AssetHistorySnapshot::query()->create([
            'user_id' => $import->user_id,
            'captured_on' => $capturedOn,
            'source_name' => 'money_forward',
            ...$values,
        ]);
    }
}
