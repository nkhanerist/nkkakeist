<?php

namespace App\Actions\Transactions;

use App\Models\Transaction;

class UpdateTransactionAction
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(Transaction $transaction, array $attributes): Transaction
    {
        $transaction->update($attributes);

        return $transaction->refresh();
    }
}
