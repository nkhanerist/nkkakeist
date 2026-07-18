<?php

namespace App\Http\Requests\Subcategories;

use App\Models\Subcategory;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSubcategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $subcategory = $this->route('subcategory');

        return $subcategory instanceof Subcategory
            && ($this->user()?->can('update', $subcategory) ?? false);
    }

    /**
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        /** @var Subcategory $subcategory */
        $subcategory = $this->route('subcategory');

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('subcategories')
                    ->where(
                        fn ($query) => $query->where('category_id', $subcategory->category_id),
                    )
                    ->ignore($subcategory->id),
            ],
            'display_order' => ['nullable', 'integer', 'min:0', 'max:999999'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'display_order' => $this->filled('display_order') ? $this->input('display_order') : 0,
            'is_active' => $this->boolean('is_active'),
        ]);
    }
}
