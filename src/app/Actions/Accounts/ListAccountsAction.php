<?php

namespace App\Actions\Accounts;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class ListAccountsAction
{
    public function handle(User $user): Collection
    {
        return $user->accounts()
            ->orderBy('display_order')
            ->orderBy('id')
            ->get();
    }
}
