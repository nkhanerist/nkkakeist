<?php

namespace App\Http\Requests\Imports;

use App\Models\Account;
use App\Models\Import;
use App\Models\ImportRow;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateImportRowAccountRequest extends FormRequest
{
    public static function errorKey(ImportRow|int $importRow): string
    {
        $rowId = $importRow instanceof ImportRow ? $importRow->id : $importRow;

        return "resolved_account_id.{$rowId}";
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
            'resolved_account_id' => ['nullable'],
            'remember_mapping' => ['boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $value = $this->input('resolved_account_id');

            if ($this->boolean('remember_mapping') && $value === null) {
                $importRow = $this->route('import_row');
                $errorKey = $importRow instanceof ImportRow
                    ? self::errorKey($importRow)
                    : 'resolved_account_id';
                $validator->errors()->add($errorKey, trans('imports.messages.account_mapping_required'));

                return;
            }

            if ($value === null) {
                return;
            }

            $importRow = $this->route('import_row');
            $errorKey = $importRow instanceof ImportRow
                ? self::errorKey($importRow)
                : 'resolved_account_id';

            if (! is_numeric($value) || (string) (int) $value !== (string) $value) {
                $validator->errors()->add($errorKey, trans('imports.messages.account_mapping_invalid'));

                return;
            }

            $exists = Account::query()
                ->whereKey((int) $value)
                ->where('user_id', $this->user()?->id)
                ->where('is_active', true)
                ->exists();

            if (! $exists) {
                $validator->errors()->add($errorKey, trans('imports.messages.account_mapping_not_found'));
            }
        });
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'resolved_account_id' => $this->filled('resolved_account_id')
                ? $this->input('resolved_account_id')
                : null,
            'remember_mapping' => $this->boolean('remember_mapping'),
        ]);
    }
}
