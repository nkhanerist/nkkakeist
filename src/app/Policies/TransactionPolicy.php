<?php

namespace App\Policies;

use App\Models\Transaction;
use App\Models\User;

class TransactionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user !== null;
    }

    public function view(User $user, Transaction $transaction): bool
    {
        return $user->is($transaction->user);
    }

    public function create(User $user): bool
    {
        return $user !== null;
    }

    public function update(User $user, Transaction $transaction): bool
    {
        return $user->is($transaction->user);
    }

    public function delete(User $user, Transaction $transaction): bool
    {
        return $user->is($transaction->user);
    }
}
