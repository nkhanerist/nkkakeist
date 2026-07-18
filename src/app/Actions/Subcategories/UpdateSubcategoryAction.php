<?php

namespace App\Actions\Subcategories;

use App\Models\Subcategory;

class UpdateSubcategoryAction
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(Subcategory $subcategory, array $attributes): Subcategory
    {
        $subcategory->update($attributes);

        return $subcategory->refresh();
    }
}
