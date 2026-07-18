<?php

namespace App\Actions\Categories;

use App\Models\Category;

class UpdateCategoryAction
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(Category $category, array $attributes): Category
    {
        $category->update($attributes);

        return $category->refresh();
    }
}
