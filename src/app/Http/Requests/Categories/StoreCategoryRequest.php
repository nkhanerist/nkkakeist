<?php

namespace App\Http\Requests\Categories;

use App\Models\Category;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Category::class) ?? false;
    }

    /**
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('categories')->where(
                    fn ($query) => $query->where('user_id', $this->user()->id),
                ),
            ],
            'type' => ['required', 'string', Rule::in(Category::types())],
            'color' => ['nullable', 'string', 'max:32', 'regex:/^#?[0-9A-Fa-f]{6}$/'],
            'icon' => ['nullable', 'string', 'max:64'],
            'display_order' => ['nullable', 'integer', 'min:0', 'max:999999'],
            'is_active' => ['required', 'boolean'],
            'return_to' => ['nullable', 'string', Rule::in(['category-review'])],
            'review_status' => ['nullable', 'string', Rule::in(['high', 'manual', 'all'])],
            'review_type' => ['nullable', 'string', Rule::in(['expense', 'income', 'all'])],
        ];
    }

    protected function prepareForValidation(): void
    {
        $color = (string) $this->input('color', '');

        $this->merge([
            'color' => $color === ''
                ? null
                : (str_starts_with($color, '#') ? $color : '#'.$color),
            'icon' => $this->filled('icon') ? $this->input('icon') : null,
            'display_order' => $this->filled('display_order') ? $this->input('display_order') : 0,
            'is_active' => $this->boolean('is_active'),
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        /** @var array<string, string> $attributes */
        $attributes = trans('categories.fields');

        return $attributes;
    }
}
