<?php

namespace App\Actions\Imports;

use App\Models\Import;
use App\Services\Imports\AssetHistoryImportValidationService;
use App\Services\Imports\BalanceSnapshotImportValidationService;
use App\Services\Imports\ImportRowValidationService;

class BuildImportPreviewAction
{
    public function __construct(
        private readonly ImportRowValidationService $importRowValidationService,
        private readonly BalanceSnapshotImportValidationService $balanceSnapshotImportValidationService,
        private readonly AssetHistoryImportValidationService $assetHistoryImportValidationService,
    ) {}

    public function handle(Import $import): Import
    {
        if ($import->status === 'failed') {
            return $import->fresh(['account', 'importRows']);
        }

        if ($import->source_name === 'balance_snapshot') {
            return $this->balanceSnapshotImportValidationService->handle($import);
        }

        if ($import->source_name === 'asset_history') {
            return $this->assetHistoryImportValidationService->handle($import);
        }

        return $this->importRowValidationService->handle($import);
    }
}
