<?php

namespace App\Actions\Subcategories;

use App\Models\Subcategory;
use App\Models\User;

class StoreSubcategoryAction
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(User $user, array $attributes): Subcategory
    {
        return $user->subcategories()->create($attributes);
    }
}
