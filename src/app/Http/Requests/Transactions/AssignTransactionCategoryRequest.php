<?php

namespace App\Http\Requests\Transactions;

use App\Models\ClassificationRule;
use App\Models\Transaction;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class AssignTransactionCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $transaction = $this->route('transaction');

        return $transaction instanceof Transaction
            ? ($this->user()?->can('update', $transaction) ?? false)
            : false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $transaction = $this->route('transaction');
        $transactionType = $transaction instanceof Transaction ? $transaction->type : null;

        return [
            'category_id' => [
                'required',
                'integer',
                Rule::exists('categories', 'id')->where(function ($query) use ($transactionType): void {
                    $query
                        ->where('user_id', $this->user()->id)
                        ->where('name', '!=', '未分類');

                    if (in_array($transactionType, ['income', 'expense'], true)) {
                        $query->whereIn('type', [$transactionType, 'both']);
                    }
                }),
            ],
            'subcategory_id' => [
                'nullable',
                'integer',
                Rule::exists('subcategories', 'id')->where(
                    fn ($query) => $query
                        ->where('user_id', $this->user()->id)
                        ->where('category_id', $this->input('category_id'))
                        ->where('name', '!=', '未分類'),
                ),
            ],
            'create_rule' => ['required', 'boolean'],
            'rule_match_field' => [
                'nullable',
                'required_if:create_rule,true',
                'string',
                Rule::in(ClassificationRule::matchFields()),
            ],
            'rule_match_operator' => [
                'nullable',
                'required_if:create_rule,true',
                'string',
                Rule::in(ClassificationRule::matchOperators()),
            ],
            'rule_match_value' => [
                'nullable',
                'required_if:create_rule,true',
                'string',
                'max:255',
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'subcategory_id' => $this->filled('subcategory_id')
                ? $this->input('subcategory_id')
                : null,
            'create_rule' => $this->boolean('create_rule'),
            'rule_match_value' => $this->filled('rule_match_value')
                ? trim((string) $this->input('rule_match_value'))
                : null,
        ]);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $transaction = $this->route('transaction');

            if (! $transaction instanceof Transaction) {
                return;
            }

            if (! in_array($transaction->type, ['income', 'expense'], true)) {
                $validator->errors()->add('category_id', '振替取引にはカテゴリを設定できません。');
            }

            if ($transaction->category_id !== null) {
                $validator->errors()->add('category_id', 'この取引のカテゴリはすでに設定されています。');
            }

            if (! $this->boolean('create_rule')) {
                return;
            }

            $matchField = (string) $this->input('rule_match_field');
            $matchOperator = (string) $this->input('rule_match_operator');
            $matchValue = (string) $this->input('rule_match_value');
            $fieldValue = match ($matchField) {
                'merchant_name' => $transaction->merchant_name,
                'description' => $transaction->description,
                'account_name' => $transaction->account?->name,
                default => null,
            };

            if (! $this->matches((string) ($fieldValue ?? ''), $matchOperator, $matchValue)) {
                $validator->errors()->add(
                    'rule_match_value',
                    'この条件は現在の取引に一致しません。対象項目と一致値を確認してください。',
                );

                return;
            }

            $normalizedMatchValue = $this->normalize($matchValue);
            $duplicate = ClassificationRule::query()
                ->where('user_id', $this->user()->id)
                ->where('transaction_type', $transaction->type)
                ->where('match_field', $matchField)
                ->where('match_operator', $matchOperator)
                ->get(['match_value'])
                ->contains(fn (ClassificationRule $rule): bool => $this->normalize($rule->match_value) === $normalizedMatchValue);

            if ($duplicate) {
                $validator->errors()->add(
                    'create_rule',
                    '同じ条件の分類ルールがすでにあります。既存ルールを編集するか、今回はチェックを外してください。',
                );
            }
        });
    }

    private function matches(string $fieldValue, string $operator, string $matchValue): bool
    {
        $normalizedFieldValue = $this->normalize($fieldValue);
        $normalizedMatchValue = $this->normalize($matchValue);

        if ($normalizedFieldValue === '' || $normalizedMatchValue === '') {
            return false;
        }

        return match ($operator) {
            'equals' => $normalizedFieldValue === $normalizedMatchValue,
            'starts_with' => str_starts_with($normalizedFieldValue, $normalizedMatchValue),
            'contains' => str_contains($normalizedFieldValue, $normalizedMatchValue),
            default => false,
        };
    }

    private function normalize(string $value): string
    {
        return Str::lower(Str::squish($value));
    }
}
