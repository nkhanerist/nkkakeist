<?php

namespace App\Actions\Transactions;

use App\Models\Transaction;
use App\Models\User;

class StoreTransactionAction
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(User $user, array $attributes): Transaction
    {
        return $user->transactions()->create($attributes);
    }
}
