<?php

namespace App\Actions\Imports;

use App\Http\Requests\Imports\UpdateImportRowAccountRequest;
use App\Models\Import;
use App\Models\ImportRow;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateImportRowAccountAction
{
    public function __construct(
        private readonly BuildImportPreviewAction $buildImportPreviewAction,
    ) {}

    public function handle(Import $import, ImportRow $importRow, ?int $accountId): Import
    {
        $errorKey = UpdateImportRowAccountRequest::errorKey($importRow);

        if ($import->status === 'imported') {
            throw ValidationException::withMessages([
                $errorKey => '取込済みの import は再編集できません。',
            ]);
        }

        if ($import->source_name !== 'balance_snapshot' || $importRow->import_id !== $import->id) {
            throw ValidationException::withMessages([
                $errorKey => '公式残高の取込行だけ取込先口座を更新できます。',
            ]);
        }

        return DB::transaction(function () use ($import, $importRow, $accountId): Import {
            $importRow->update([
                'manual_resolved_account_id' => $accountId,
            ]);

            return $this->buildImportPreviewAction->handle($import->fresh());
        });
    }
}
