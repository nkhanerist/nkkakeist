<?php

namespace App\Http\Requests\Imports;

use App\Models\Import;
use App\Models\ImportRow;
use Illuminate\Foundation\Http\FormRequest;

class UpdateImportRowReplacementRequest extends FormRequest
{
    public static function errorKey(ImportRow|int $importRow): string
    {
        $rowId = $importRow instanceof ImportRow ? $importRow->id : $importRow;

        return "replace_existing.{$rowId}";
    }

    public function authorize(): bool
    {
        $import = $this->route('import');
        $importRow = $this->route('import_row');

        return $import instanceof Import
            && $import->source_name === 'balance_snapshot'
            && $importRow instanceof ImportRow
            && $importRow->import_id === $import->id
            && $import->status !== 'imported'
            && ($this->user()?->can('view', $import) ?? false);
    }

    public function rules(): array
    {
        return [
            'replace_existing' => ['required', 'boolean'],
        ];
    }
}
