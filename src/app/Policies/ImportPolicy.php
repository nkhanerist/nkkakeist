<?php

namespace App\Policies;

use App\Models\Import;
use App\Models\User;

class ImportPolicy
{
    public function viewAny(User $user): bool
    {
        return $user !== null;
    }

    public function view(User $user, Import $import): bool
    {
        return $user->is($import->user);
    }

    public function create(User $user): bool
    {
        return $user !== null;
    }

    public function delete(User $user, Import $import): bool
    {
        return $user->is($import->user);
    }

    public function commit(User $user, Import $import): bool
    {
        return $user->is($import->user);
    }
}
