<?php

namespace App\Policies;

use App\Models\Account;
use App\Models\User;

class AccountPolicy
{
    public function viewAny(User $user): bool
    {
        return $user !== null;
    }

    public function view(User $user, Account $account): bool
    {
        return $user->is($account->user);
    }

    public function create(User $user): bool
    {
        return $user !== null;
    }

    public function update(User $user, Account $account): bool
    {
        return $user->is($account->user);
    }

    public function delete(User $user, Account $account): bool
    {
        return $user->is($account->user);
    }
}
