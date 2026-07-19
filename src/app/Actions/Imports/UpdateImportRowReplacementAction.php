<?php

namespace App\Actions\Imports;

use App\Http\Requests\Imports\UpdateImportRowReplacementRequest;
use App\Models\Account;
use App\Models\Import;
use App\Models\ImportRow;
use App\Services\Imports\BalanceSnapshotConflictService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateImportRowReplacementAction
{
    public function __construct(
        private readonly BuildImportPreviewAction $buildImportPreviewAction,
        private readonly BalanceSnapshotConflictService $conflictService,
    ) {}

    public function handle(Import $import, ImportRow $importRow, bool $replaceExisting): Import
    {
        $errorKey = UpdateImportRowReplacementRequest::errorKey($importRow);

        if (
            $import->status === 'imported'
            || $import->source_name !== 'balance_snapshot'
            || $importRow->import_id !== $import->id
        ) {
            throw ValidationException::withMessages([
                $errorKey => trans('imports.messages.replacement_not_allowed'),
            ]);
        }

        return DB::transaction(function () use ($import, $importRow, $replaceExisting, $errorKey): Import {
            if (! $replaceExisting) {
                $importRow->update(['replace_account_snapshot_id' => null]);

                return $this->buildImportPreviewAction->handle($import->fresh());
            }

            $account = $importRow->resolvedAccount;
            $snapshot = $this->conflictService->find($import, $importRow, $account);

            if (
                ! $account instanceof Account
                || $snapshot === null
                || $importRow->is_duplicate_candidate
            ) {
                throw ValidationException::withMessages([
                    $errorKey => trans('imports.messages.replacement_snapshot_not_found'),
                ]);
            }

            $importRow->update([
                'replace_account_snapshot_id' => $snapshot->id,
            ]);

            return $this->buildImportPreviewAction->handle($import->fresh());
        });
    }
}
