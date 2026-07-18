<?php

namespace App\Policies;

use App\Models\Subcategory;
use App\Models\User;

class SubcategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user !== null;
    }

    public function view(User $user, Subcategory $subcategory): bool
    {
        return $user->is($subcategory->user);
    }

    public function create(User $user): bool
    {
        return $user !== null;
    }

    public function update(User $user, Subcategory $subcategory): bool
    {
        return $user->is($subcategory->user);
    }

    public function delete(User $user, Subcategory $subcategory): bool
    {
        return $user->is($subcategory->user);
    }
}
