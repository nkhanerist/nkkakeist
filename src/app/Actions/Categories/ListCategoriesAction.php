<?php

namespace App\Actions\Categories;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class ListCategoriesAction
{
    public function handle(User $user): Collection
    {
        return $user->categories()
            ->with([
                'subcategories' => fn ($query) => $query
                    ->orderBy('display_order')
                    ->orderBy('id'),
            ])
            ->orderBy('display_order')
            ->orderBy('id')
            ->get();
    }
}
