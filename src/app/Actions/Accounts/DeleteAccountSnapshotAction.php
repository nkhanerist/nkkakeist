<?php

namespace App\Actions\Accounts;

use App\Models\AccountSnapshot;

class DeleteAccountSnapshotAction
{
    public function handle(AccountSnapshot $snapshot): void
    {
        $snapshot->delete();
    }
}
