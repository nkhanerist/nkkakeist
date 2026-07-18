<?php

namespace App\Actions\Accounts;

use App\Models\Account;

class UpdateAccountAction
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(Account $account, array $attributes): Account
    {
        $account->update($attributes);

        return $account->refresh();
    }
}
