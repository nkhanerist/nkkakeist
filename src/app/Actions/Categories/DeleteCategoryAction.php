<?php

namespace App\Actions\Categories;

use App\Models\Category;

class DeleteCategoryAction
{
    public function handle(Category $category): void
    {
        $category->delete();
    }
}
