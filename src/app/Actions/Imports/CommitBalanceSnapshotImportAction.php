<?php

namespace App\Actions\Imports;

use App\Models\Account;
use App\Models\AccountSnapshot;
use App\Models\AssetHistorySnapshot;
use App\Models\Import;
use App\Models\ImportRow;
use App\Models\InvestmentPositionSnapshot;
use App\Services\Imports\BalanceSnapshotConflictService;
use App\Services\Imports\BalanceSnapshotImportValidationService;
use App\Services\Imports\InvestmentPositionIdentityService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CommitBalanceSnapshotImportAction
{
    public function __construct(
        private readonly BalanceSnapshotImportValidationService $validationService,
        private readonly BalanceSnapshotConflictService $conflictService,
        private readonly InvestmentPositionIdentityService $positionIdentityService,
    ) {}

    public function handle(Import $import): Import
    {
        if ($import->status === 'imported') {
            throw ValidationException::withMessages([
                'import' => trans('imports.action_errors.already_imported'),
            ]);
        }

        if ($import->status !== 'validated') {
            throw ValidationException::withMessages([
                'import' => trans('imports.action_errors.balance_preview_required'),
            ]);
        }

        $import = $this->validationService->handle($import);
        $import->loadMissing('user', 'importRows.resolvedAccount');

        if ($import->importRows->contains(fn (ImportRow $row): bool => $row->status === 'error')) {
            throw ValidationException::withMessages([
                'import' => trans('imports.action_errors.balance_unresolved'),
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
                'import' => trans('imports.action_errors.replacement_stale'),
            ]);
        }

        $replacementAudit = [
            'id' => $snapshot->id,
            'import_id' => $snapshot->import_id,
            'balance' => (string) $snapshot->balance,
            'captured_at' => $snapshot->captured_at->toIso8601String(),
            'source_name' => $snapshot->source_name,
        ];

        $snapshot->delete();

        return $this->createSnapshot($import, $importRow, $replacementAudit);
    }

    /**
     * @param  array{id: int, import_id: int|null, balance: string, captured_at: string, source_name: string|null}|null  $replacementAudit
     */
    private function createSnapshot(
        Import $import,
        ImportRow $importRow,
        ?array $replacementAudit = null,
    ): AccountSnapshot {
        $account = $importRow->resolvedAccount;

        if (! $account instanceof Account || $account->user_id !== $import->user_id) {
            throw ValidationException::withMessages([
                'import' => trans('imports.action_errors.balance_account_unresolved'),
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
                'import' => trans('imports.action_errors.balance_value_missing'),
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
            'note' => $replacementAudit === null
                ? '公式残高取込から記録'
                : '公式残高取込から同日残高を置き換え',
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
                'replaced_snapshot' => $replacementAudit,
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
        $existingPositions = $snapshot->investmentPositions()
            ->get(['id', 'position_key', 'instrument_name', 'currency']);
        $sourceAccountName = is_string($rawPayload['source_account_name'] ?? null)
            ? $rawPayload['source_account_name']
            : null;

        foreach ($positions as $position) {
            if (! is_array($position)) {
                continue;
            }

            $instrumentName = (string) ($position['instrument_name'] ?? '');
            $currency = (string) ($position['currency'] ?? 'JPY');
            $positionKey = $this->positionIdentityService->positionKey(
                $position,
                $source,
                $sourceAccountName,
            );
            $semanticKey = $this->positionIdentityService->semanticKey($instrumentName, $currency);

            if ($existingPositions->contains(
                fn (InvestmentPositionSnapshot $existing): bool => $existing->position_key === $positionKey
                    || $this->positionIdentityService->semanticKey(
                        $existing->instrument_name,
                        $existing->currency,
                    ) === $semanticKey,
            )) {
                continue;
            }

            $createdPosition = $snapshot->investmentPositions()->create([
                'user_id' => $snapshot->user_id,
                'account_id' => $snapshot->account_id,
                'import_id' => $import->id,
                'captured_at' => $snapshot->captured_at,
                'position_key' => $positionKey,
                'instrument_name' => $instrumentName,
                'instrument_code' => $position['instrument_code'] ?? null,
                'external_id' => $position['external_id'] ?? null,
                'asset_class' => $position['asset_class'] ?? null,
                'quantity' => $position['quantity'] ?? null,
                'average_acquisition_price' => $position['average_acquisition_price'] ?? null,
                'unit_price' => $position['unit_price'] ?? null,
                'acquisition_cost' => $position['acquisition_cost'] ?? null,
                'valuation' => $position['valuation'],
                'unrealized_gain' => $position['unrealized_gain'] ?? null,
                'currency' => $currency,
                'source_name' => $this->sourceLabel($source),
                'metadata' => [
                    'source' => $source,
                    'source_account_name' => $rawPayload['source_account_name'] ?? null,
                ],
            ]);
            $existingPositions->push($createdPosition);
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
