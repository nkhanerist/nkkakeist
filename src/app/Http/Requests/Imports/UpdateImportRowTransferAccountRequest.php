<?php

namespace App\Http\Requests\Imports;

use App\Models\Account;
use App\Models\Import;
use App\Models\ImportRow;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateImportRowTransferAccountRequest extends FormRequest
{
    public static function errorKey(ImportRow|int $importRow): string
    {
        $rowId = $importRow instanceof ImportRow ? $importRow->id : $importRow;

        return "resolved_transfer_account_id.{$rowId}";
    }

    public function authorize(): bool
    {
        $import = $this->route('import');
        $importRow = $this->route('import_row');

        return $import instanceof Import
            && $importRow instanceof ImportRow
            && $importRow->import_id === $import->id
            && $importRow->detected_type === 'transfer'
            && $import->status !== 'imported'
            && ($this->user()?->can('view', $import) ?? false);
    }

    public function rules(): array
    {
        return [
            'resolved_transfer_account_id' => [
                'nullable',
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'resolved_transfer_account_id' => $this->filled('resolved_transfer_account_id')
                ? $this->input('resolved_transfer_account_id')
                : null,
        ]);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $value = $this->input('resolved_transfer_account_id');

            if ($value === null) {
                return;
            }

            $importRow = $this->route('import_row');
            $errorKey = $importRow instanceof ImportRow
                ? self::errorKey($importRow)
                : 'resolved_transfer_account_id';

            if (! is_numeric($value) || (string) (int) $value !== (string) $value) {
                $validator->errors()->add($errorKey, '相手口座の指定が不正です。');

                return;
            }

            $accountExists = Account::query()
                ->where('id', (int) $value)
                ->where('user_id', $this->user()?->id)
                ->exists();

            if (! $accountExists) {
                $validator->errors()->add($errorKey, '選択した相手口座が見つかりません。');

                return;
            }

            if (
                $importRow instanceof ImportRow
                && ($sourceAccount = $this->csvSourceAccount($importRow)) instanceof Account
                && $sourceAccount->id === (int) $value
            ) {
                $validator->errors()->add($errorKey, '振替元と同じ口座は相手口座に指定できません。');
            }
        });
    }

    private function csvSourceAccount(ImportRow $importRow): ?Account
    {
        $accountName = $this->normalizeText($importRow->account_name ?? '');

        if ($accountName === '' || $this->user() === null) {
            return null;
        }

        $accounts = $this->user()->accounts()->get();

        $matchedAccounts = $accounts->filter(function (Account $account) use ($accountName): bool {
            $candidates = collect([$account->name, ...($account->import_aliases ?? [])])
                ->filter(fn ($value): bool => is_string($value) && trim($value) !== '')
                ->map(fn (string $value): string => $this->normalizeText($value))
                ->unique();

            return $candidates->contains($accountName);
        })->values();

        return $matchedAccounts->count() === 1 ? $matchedAccounts->first() : null;
    }

    private function normalizeText(string $value): string
    {
        return mb_strtolower(trim(mb_convert_kana($value, 'asKV', 'UTF-8')), 'UTF-8');
    }
}
