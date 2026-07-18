<?php

namespace App\Actions\Transactions;

use App\Models\Transaction;

class DeleteTransactionAction
{
    public function handle(Transaction $transaction): void
    {
        $transaction->delete();
    }
}
