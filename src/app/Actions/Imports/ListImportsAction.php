<?php

namespace App\Actions\Imports;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ListImportsAction
{
    public function handle(User $user): LengthAwarePaginator
    {
        return $user->imports()
            ->with('account')
            ->latest()
            ->paginate(15)
            ->withQueryString();
    }
}
