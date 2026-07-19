<?php

namespace App\Services\Imports;

use App\Models\Import;
use App\Models\ImportRow;

class FindEditableReimportService
{
    public function __construct(
        private readonly ImportRowIssueService $importRowIssueService,
    ) {}

    /**
     * @return array{id: int, original_filename: string, row_id: int, row_number: int}|null
     */
    public function handle(Import $import): ?array
    {
        if ($import->status !== 'imported') {
            return null;
        }

        $import->loadMissing('importRows');
        $signatures = $import->importRows
            ->filter(fn (ImportRow $row): bool => $this->importRowIssueService->partition(
                $row->validation_errors ?? [],
            )['errors'] !== [])
            ->map(fn (ImportRow $row): array => $this->signature($row))
            ->values()
            ->all();

        if ($signatures === []) {
            return null;
        }

        $candidates = $import->user->imports()
            ->whereKeyNot($import->id)
            ->where('source_name', $import->source_name)
            ->whereIn('status', ['uploaded', 'parsed', 'validated'])
            ->with('importRows')
            ->latest('id')
            ->limit(20)
            ->get();

        $candidate = null;
        $candidateRow = null;

        foreach ($candidates as $possibleCandidate) {
            $matchingRow = $possibleCandidate->importRows->first(
                fn (ImportRow $row): bool => in_array($this->signature($row), $signatures, true),
            );

            if ($matchingRow !== null) {
                $candidate = $possibleCandidate;
                $candidateRow = $matchingRow;

                break;
            }
        }

        if ($candidate === null || $candidateRow === null) {
            return null;
        }

        return [
            'id' => $candidate->id,
            'original_filename' => $candidate->original_filename,
            'row_id' => $candidateRow->id,
            'row_number' => $candidateRow->row_number,
        ];
    }

    /**
     * @return array<string, string|null>
     */
    private function signature(ImportRow $row): array
    {
        return [
            'transaction_date' => $row->transaction_date?->toDateString(),
            'amount' => $row->amount,
            'account_name' => $row->account_name,
            'merchant_name' => $row->merchant_name,
            'description' => $row->description,
            'detected_type' => $row->detected_type,
        ];
    }
}
