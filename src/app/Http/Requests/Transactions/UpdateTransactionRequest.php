<?php

namespace App\Http\Requests\Transactions;

use App\Models\Transaction;
use Illuminate\Validation\Validator;

class UpdateTransactionRequest extends StoreTransactionRequest
{
    public function authorize(): bool
    {
        $transaction = $this->route('transaction');

        return $transaction instanceof Transaction
            ? ($this->user()?->can('update', $transaction) ?? false)
            : false;
    }

    protected function prepareForValidation(): void
    {
        $hasCalculationTarget = $this->exists('is_calculation_target');
        $hasAffectsAccountBalance = $this->exists('affects_account_balance');

        parent::prepareForValidation();

        if ($hasCalculationTarget) {
            if ($hasAffectsAccountBalance) {
                return;
            }
        }

        $transaction = $this->route('transaction');

        if ($transaction instanceof Transaction) {
            $preserved = [];

            if (! $hasCalculationTarget) {
                $preserved['is_calculation_target'] = $transaction->is_calculation_target;
            }

            if (! $hasAffectsAccountBalance) {
                $preserved['affects_account_balance'] = $transaction->affects_account_balance;
            }

            $this->merge($preserved);
        }
    }

    public function withValidator(Validator $validator): void
    {
        $transaction = $this->route('transaction');

        $this->addCurrencyConsistencyValidation(
            $validator,
            $transaction instanceof Transaction ? $transaction : null,
        );
    }
}
