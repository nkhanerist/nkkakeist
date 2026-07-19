<?php

namespace App\Http\Requests\Transactions;

use App\Models\Account;
use App\Models\Subcategory;
use App\Models\Transaction;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Transaction::class) ?? false;
    }

    /**
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        return [
            'transaction_date' => ['required', 'date'],
            'type' => ['required', 'string', Rule::in(Transaction::types())],
            'account_id' => [
                'required',
                'integer',
                Rule::exists('accounts', 'id')->where(
                    fn ($query) => $query->where('user_id', $this->user()->id),
                ),
            ],
            'transfer_account_id' => [
                Rule::requiredIf($this->input('type') === 'transfer'),
                'nullable',
                'integer',
                'different:account_id',
                Rule::exists('accounts', 'id')->where(
                    fn ($query) => $query->where('user_id', $this->user()->id),
                ),
            ],
            'amount' => ['required', 'numeric', 'gt:0', 'max:999999999999.99'],
            'currency' => ['nullable', 'string', 'size:3', 'regex:/^[A-Z]{3}$/'],
            'merchant_name' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'category_id' => [
                Rule::requiredIf(in_array($this->input('type'), ['income', 'expense'], true)),
                'nullable',
                'integer',
                Rule::exists('categories', 'id')->where(function ($query): void {
                    $type = $this->input('type');

                    $query->where('user_id', $this->user()->id);

                    if (in_array($type, ['income', 'expense'], true)) {
                        $query->whereIn('type', [$type, 'both']);
                    }
                }),
            ],
            'subcategory_id' => [
                'nullable',
                'integer',
                Rule::exists('subcategories', 'id')->where(
                    fn ($query) => $query->where('user_id', $this->user()->id),
                ),
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if ($value === null || $value === '') {
                        return;
                    }

                    $categoryId = $this->input('category_id');

                    if ($categoryId === null || $categoryId === '') {
                        $fail(trans('transactions.messages.category_required_for_subcategory'));

                        return;
                    }

                    $exists = Subcategory::query()
                        ->whereKey($value)
                        ->where('user_id', $this->user()->id)
                        ->where('category_id', $categoryId)
                        ->exists();

                    if (! $exists) {
                        $fail(trans('transactions.messages.subcategory_mismatch'));
                    }
                },
            ],
            'payment_method_label' => ['nullable', 'string', 'max:255'],
            'is_confirmed' => ['required', 'boolean'],
            'is_calculation_target' => ['required', 'boolean'],
            'affects_account_balance' => ['required', 'boolean'],
            'memo' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        /** @var array<string, string> $attributes */
        $attributes = trans('transactions.fields');

        return $attributes;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'currency' => strtoupper((string) $this->input('currency', 'JPY')),
            'merchant_name' => $this->filled('merchant_name') ? $this->input('merchant_name') : null,
            'description' => $this->filled('description') ? $this->input('description') : null,
            'category_id' => $this->filled('category_id') ? $this->input('category_id') : null,
            'subcategory_id' => $this->filled('subcategory_id') ? $this->input('subcategory_id') : null,
            'transfer_account_id' => $this->filled('transfer_account_id') ? $this->input('transfer_account_id') : null,
            'payment_method_label' => $this->filled('payment_method_label') ? $this->input('payment_method_label') : null,
            'memo' => $this->filled('memo') ? $this->input('memo') : null,
            'is_confirmed' => $this->boolean('is_confirmed'),
            'is_calculation_target' => $this->boolean('is_calculation_target', true),
            'affects_account_balance' => $this->boolean('affects_account_balance', true),
        ]);
    }

    public function withValidator(Validator $validator): void
    {
        $this->addCurrencyConsistencyValidation($validator);
    }

    /**
     * @return array<string, mixed>
     */
    public function validated($key = null, $default = null): array
    {
        $validated = parent::validated($key, $default);

        if (($validated['type'] ?? null) === 'transfer') {
            $validated['category_id'] = null;
            $validated['subcategory_id'] = null;
            $validated['affects_account_balance'] = true;
        } else {
            $validated['transfer_account_id'] = null;
        }

        return $validated;
    }

    protected function addCurrencyConsistencyValidation(Validator $validator, ?Transaction $existingTransaction = null): void
    {
        $validator->after(function (Validator $validator) use ($existingTransaction): void {
            if ($this->preservesExistingCurrencyShape($existingTransaction)) {
                return;
            }

            $accountId = $this->input('account_id');

            if (! $accountId) {
                return;
            }

            $accountIds = [(int) $accountId];
            $transferAccountId = $this->input('transfer_account_id');

            if ($transferAccountId) {
                $accountIds[] = (int) $transferAccountId;
            }

            $accounts = Account::query()
                ->where('user_id', $this->user()->id)
                ->whereIn('id', $accountIds)
                ->get(['id', 'currency'])
                ->keyBy('id');

            $sourceAccount = $accounts->get((int) $accountId);

            if (! $sourceAccount) {
                return;
            }

            if ($this->input('currency') !== $sourceAccount->currency) {
                $validator->errors()->add(
                    'currency',
                    trans('transactions.messages.account_currency_mismatch'),
                );
            }

            if ($this->input('type') !== 'transfer' || ! $transferAccountId) {
                return;
            }

            $destinationAccount = $accounts->get((int) $transferAccountId);

            if (! $destinationAccount) {
                return;
            }

            if ($sourceAccount->currency !== $destinationAccount->currency) {
                $validator->errors()->add(
                    'transfer_account_id',
                    trans('transactions.messages.transfer_currency_mismatch'),
                );
            }
        });
    }

    protected function preservesExistingCurrencyShape(?Transaction $existingTransaction): bool
    {
        if (! $existingTransaction instanceof Transaction) {
            return false;
        }

        return $this->input('type') === $existingTransaction->type
            && (int) $this->input('account_id') === $existingTransaction->account_id
            && $this->nullableInteger($this->input('transfer_account_id')) === $existingTransaction->transfer_account_id
            && $this->input('currency') === $existingTransaction->currency;
    }

    protected function nullableInteger(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }
}
