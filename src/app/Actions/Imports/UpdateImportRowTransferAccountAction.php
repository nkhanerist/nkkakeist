<?php

namespace App\Actions\Imports;

use App\Http\Requests\Imports\UpdateImportRowTransferAccountRequest;
use App\Models\Import;
use App\Models\ImportRow;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateImportRowTransferAccountAction
{
    public function __construct(
        private readonly BuildImportPreviewAction $buildImportPreviewAction,
    ) {}

    public function handle(Import $import, ImportRow $importRow, ?int $resolvedTransferAccountId): Import
    {
        $errorKey = UpdateImportRowTransferAccountRequest::errorKey($importRow);

        if ($import->status === 'imported') {
            throw ValidationException::withMessages([
                $errorKey => '取込済みの import は再編集できません。',
            ]);
        }

        if ($importRow->import_id !== $import->id || $importRow->detected_type !== 'transfer') {
            throw ValidationException::withMessages([
                $errorKey => '振替行のみ相手口座を更新できます。',
            ]);
        }

        return DB::transaction(function () use ($import, $importRow, $resolvedTransferAccountId, $errorKey): Import {
            $importRow->update([
                'manual_resolved_transfer_account_id' => $resolvedTransferAccountId,
            ]);

            $revalidatedImport = $this->buildImportPreviewAction->handle($import->fresh());
            $updatedRow = $revalidatedImport->importRows->firstWhere('id', $importRow->id);

            if (
                $resolvedTransferAccountId !== null
                && $updatedRow instanceof ImportRow
                && $updatedRow->status === 'error'
                && $this->hasTransferResolutionErrors($updatedRow)
            ) {
                throw ValidationException::withMessages([
                    $errorKey => $updatedRow->validation_errors ?? ['相手口座を更新できません。'],
                ]);
            }

            return $revalidatedImport;
        });
    }

    private function hasTransferResolutionErrors(ImportRow $importRow): bool
    {
        $validationErrors = $importRow->validation_errors ?? [];

        if (! is_array($validationErrors)) {
            return false;
        }

        foreach ($validationErrors as $validationError) {
            if (! is_string($validationError)) {
                continue;
            }

            if (
                str_contains($validationError, '振替元口座')
                || str_contains($validationError, '振替先')
                || str_contains($validationError, '相手口座')
                || str_contains($validationError, '振替方向')
                || str_contains($validationError, '同じ通貨')
            ) {
                return true;
            }
        }

        return false;
    }
}
