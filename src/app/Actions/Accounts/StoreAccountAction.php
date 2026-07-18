<?php

namespace App\Actions\Accounts;

use App\Models\Account;
use App\Models\User;

class StoreAccountAction
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(User $user, array $attributes): Account
    {
        return $user->accounts()->create($attributes);
    }
}
