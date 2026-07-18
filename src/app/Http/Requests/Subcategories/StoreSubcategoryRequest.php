<?php

namespace App\Http\Requests\Subcategories;

use App\Models\Category;
use App\Models\Subcategory;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSubcategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $categoryId = $this->integer('category_id');
        $user = $this->user();

        if ($user === null) {
            return false;
        }

        if ($categoryId > 0) {
            $category = Category::find($categoryId);

            if ($category instanceof Category) {
                return $user->can('update', $category);
            }
        }

        return $user->can('create', Subcategory::class);
    }

    /**
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        return [
            'category_id' => [
                'required',
                'integer',
                Rule::exists('categories', 'id')->where(
                    fn ($query) => $query->where('user_id', $this->user()->id),
                ),
            ],
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('subcategories')->where(
                    fn ($query) => $query->where('category_id', $this->integer('category_id')),
                ),
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
