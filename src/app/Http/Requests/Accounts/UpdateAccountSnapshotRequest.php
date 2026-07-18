<?php

namespace App\Http\Requests\Accounts;

use App\Models\Account;
use App\Models\AccountSnapshot;

class UpdateAccountSnapshotRequest extends StoreAccountSnapshotRequest
{
    public function authorize(): bool
    {
        $account = $this->route('account');
        $snapshot = $this->route('account_snapshot');

        return $account instanceof Account
            && $snapshot instanceof AccountSnapshot
            && $snapshot->account_id === $account->id
            && ($this->user()?->can('update', $account) ?? false);
    }
}
