<?php

namespace App\Actions\Imports;

use App\Models\Import;
use App\Services\Imports\ImportParserService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class ParseImportAction
{
    public function __construct(
        private readonly ImportParserService $importParserService,
    ) {}

    public function handle(Import $import): Import
    {
        if ($import->status === 'imported') {
            throw ValidationException::withMessages([
                'import' => trans('imports.action_errors.reparse_imported'),
            ]);
        }

        try {
            $contents = Storage::disk('local')->get($import->storage_path);
            $parsedDocument = $this->importParserService->parse(
                $import->source_name ?? 'money_forward',
                $contents,
            );
            $parsedRows = $parsedDocument['rows'];

            DB::transaction(function () use ($import, $parsedRows, $parsedDocument): void {
                $import->importRows()->delete();

                foreach ($parsedRows as $row) {
                    $import->importRows()->create([
                        ...$row,
                        'status' => 'pending',
                    ]);
                }

                $import->update([
                    'status' => 'parsed',
                    'source_metadata' => $parsedDocument['metadata'],
                    'total_rows' => count($parsedRows),
                    'imported_rows' => 0,
                    'skipped_rows' => 0,
                    'duplicate_rows' => 0,
                    'error_message' => null,
                    'imported_at' => null,
                ]);
            });
        } catch (Throwable $throwable) {
            $errorMessage = $throwable instanceof \RuntimeException
                ? $throwable->getMessage()
                : trans('imports.parse_errors.unexpected');

            if (! $throwable instanceof \RuntimeException) {
                report($throwable);
            }

            $import->importRows()->delete();
            $import->update([
                'status' => 'failed',
                'source_metadata' => null,
                'total_rows' => 0,
                'imported_rows' => 0,
                'skipped_rows' => 0,
                'duplicate_rows' => 0,
                'error_message' => $errorMessage,
                'imported_at' => null,
            ]);
        }

        return $import->fresh(['account', 'importRows']);
    }
}
