<?php

namespace App\Http\Requests\Accounts;

use App\Models\Account;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreAccountSnapshotRequest extends FormRequest
{
    public function authorize(): bool
    {
        $account = $this->route('account');

        return $account instanceof Account
            && ($this->user()?->can('update', $account) ?? false);
    }

    /**
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        return [
            'balance_date' => ['required', 'date'],
            'balance' => ['required', 'numeric', 'between:0,999999999999.99'],
            'source_name' => ['nullable', 'string', 'max:64'],
            'note' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $account = $this->route('account');

                if ($account instanceof Account && $account->balance_method !== 'snapshot') {
                    $validator->errors()->add(
                        'balance',
                        '評価額は、残高計算方式が「評価額スナップショット」の口座だけに記録できます。',
                    );
                }
            },
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'source_name' => $this->filled('source_name')
                ? trim((string) $this->input('source_name'))
                : '手動入力',
            'note' => $this->filled('note')
                ? trim((string) $this->input('note'))
                : null,
        ]);
    }
}
