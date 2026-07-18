<?php

namespace App\Http\Requests\Accounts;

use App\Models\Account;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreAccountReconciliationRequest extends FormRequest
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
            'balance_date' => ['required', 'date', 'before_or_equal:today'],
            'actual_balance' => ['required', 'numeric', 'between:-999999999999.99,999999999999.99'],
            'source_name' => ['nullable', 'string', 'max:64'],
            'note' => ['nullable', 'string', 'max:2000'],
            'confirmed' => ['accepted'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $account = $this->route('account');

                if (! $account instanceof Account) {
                    return;
                }

                if ($account->balance_method !== 'ledger') {
                    $validator->errors()->add(
                        'actual_balance',
                        '評価額方式の口座は、評価額画面から時価評価額を記録してください。',
                    );
                }

                if ($account->balance_role === 'clearing') {
                    $validator->errors()->add(
                        'actual_balance',
                        '中継口座は期首残高の照合対象ではありません。取引経路を補正してください。',
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
                : '手動照合',
            'note' => $this->filled('note')
                ? trim((string) $this->input('note'))
                : null,
        ]);
    }
}
