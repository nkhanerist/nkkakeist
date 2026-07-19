<?php

namespace App\Http\Requests\ClassificationRules;

use App\Models\Category;
use App\Models\ClassificationRule;
use App\Models\Subcategory;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreClassificationRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', ClassificationRule::class) ?? false;
    }

    /**
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'transaction_type' => ['nullable', 'string', Rule::in(ClassificationRule::applicableTransactionTypes())],
            'match_field' => ['required', 'string', Rule::in(ClassificationRule::matchFields())],
            'match_operator' => ['required', 'string', Rule::in(ClassificationRule::matchOperators())],
            'match_value' => ['required', 'string', 'max:255'],
            'category_id' => [
                'nullable',
                'integer',
                Rule::exists('categories', 'id')->where(
                    fn ($query) => $query->where('user_id', $this->user()->id),
                ),
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
                        $fail(trans('classification_rules.messages.category_required_for_subcategory'));

                        return;
                    }

                    $exists = Subcategory::query()
                        ->whereKey($value)
                        ->where('user_id', $this->user()->id)
                        ->where('category_id', $categoryId)
                        ->exists();

                    if (! $exists) {
                        $fail(trans('classification_rules.messages.subcategory_mismatch'));
                    }
                },
            ],
            'is_calculation_target' => ['nullable', 'boolean'],
            'priority' => ['required', 'integer', 'min:0', 'max:999999'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        /** @var array<string, string> $attributes */
        $attributes = trans('classification_rules.fields');

        return $attributes;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'transaction_type' => $this->filled('transaction_type') ? $this->input('transaction_type') : null,
            'match_value' => trim((string) $this->input('match_value', '')),
            'category_id' => $this->filled('category_id') ? $this->input('category_id') : null,
            'subcategory_id' => $this->filled('subcategory_id') ? $this->input('subcategory_id') : null,
            'is_calculation_target' => $this->filled('is_calculation_target')
                ? $this->boolean('is_calculation_target')
                : null,
            'priority' => $this->filled('priority') ? $this->input('priority') : 0,
            'is_active' => $this->boolean('is_active'),
        ]);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (
                $this->input('category_id') === null
                && $this->input('subcategory_id') === null
                && $this->input('is_calculation_target') === null
            ) {
                $validator->errors()->add(
                    'category_id',
                    trans('classification_rules.messages.completion_required'),
                );
            }

            $categoryId = $this->input('category_id');

            if ($categoryId === null || $categoryId === '') {
                return;
            }

            $category = Category::query()
                ->whereKey($categoryId)
                ->where('user_id', $this->user()->id)
                ->first();

            if ($category === null) {
                return;
            }

            $transactionType = $this->input('transaction_type') ?? 'any';

            if ($transactionType === 'any') {
                if ($category->type !== 'both') {
                    $validator->errors()->add(
                        'category_id',
                        trans('classification_rules.messages.any_requires_both_category'),
                    );
                }

                return;
            }

            if (
                in_array($transactionType, ['income', 'expense'], true)
                && ! in_array($category->type, [$transactionType, 'both'], true)
            ) {
                $validator->errors()->add(
                    'category_id',
                    trans('classification_rules.messages.category_type_mismatch'),
                );
            }
        });
    }
}
