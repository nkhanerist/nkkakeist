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

    /** @return array<string, string> */
    public function attributes(): array
    {
        /** @var array<string, string> $attributes */
        $attributes = trans('accounts.fields');

        return $attributes;
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
                        trans('accounts.messages.snapshot_account_required'),
                    );
                }

                if ($account->balance_role === 'clearing') {
                    $validator->errors()->add(
                        'actual_balance',
                        trans('accounts.messages.clearing_not_reconcilable'),
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
                : trans('accounts.defaults.manual_reconciliation'),
            'note' => $this->filled('note')
                ? trim((string) $this->input('note'))
                : null,
        ]);
    }
}
