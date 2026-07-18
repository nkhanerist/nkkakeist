<?php

namespace App\Actions\Transactions;

use App\Models\Transaction;

class ShowTransactionAction
{
    public function handle(Transaction $transaction): Transaction
    {
        return $transaction->load(['account', 'transferAccount', 'category', 'subcategory']);
    }
}
