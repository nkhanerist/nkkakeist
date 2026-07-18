<?php

namespace App\Http\Requests\Categories;

use App\Models\Category;
use Illuminate\Validation\Rule;

class UpdateCategoryRequest extends StoreCategoryRequest
{
    public function authorize(): bool
    {
        $category = $this->route('category');

        return $category instanceof Category
            && ($this->user()?->can('update', $category) ?? false);
    }

    public function rules(): array
    {
        /** @var Category $category */
        $category = $this->route('category');

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('categories')
                    ->where(
                        fn ($query) => $query->where('user_id', $this->user()->id),
                    )
                    ->ignore($category->id),
            ],
            'type' => ['required', 'string', Rule::in(Category::types())],
            'color' => ['nullable', 'string', 'max:32', 'regex:/^#?[0-9A-Fa-f]{6}$/'],
            'icon' => ['nullable', 'string', 'max:64'],
            'display_order' => ['nullable', 'integer', 'min:0', 'max:999999'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
