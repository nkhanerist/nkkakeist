<?php

namespace App\Actions\Categories;

use App\Models\Category;
use App\Models\User;

class StoreCategoryAction
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(User $user, array $attributes): Category
    {
        return $user->categories()->create($attributes);
    }
}
