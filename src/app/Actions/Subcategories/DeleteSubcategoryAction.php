<?php

namespace App\Actions\Subcategories;

use App\Models\Subcategory;

class DeleteSubcategoryAction
{
    public function handle(Subcategory $subcategory): void
    {
        $subcategory->delete();
    }
}
