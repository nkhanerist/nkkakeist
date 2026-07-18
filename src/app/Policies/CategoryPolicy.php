<?php

namespace App\Policies;

use App\Models\Category;
use App\Models\User;

class CategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user !== null;
    }

    public function view(User $user, Category $category): bool
    {
        return $user->is($category->user);
    }

    public function create(User $user): bool
    {
        return $user !== null;
    }

    public function update(User $user, Category $category): bool
    {
        return $user->is($category->user);
    }

    public function delete(User $user, Category $category): bool
    {
        return $user->is($category->user);
    }
}
