<?php

namespace App\Actions\Imports;

use App\Models\User;
use App\Services\Imports\ImportRowIssueService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ListImportsAction
{
    public function __construct(
        private readonly ImportRowIssueService $importRowIssueService,
    ) {}

    public function handle(User $user): LengthAwarePaginator
    {
        return $user->imports()
            ->with('account')
            ->withCount([
                'importRows as issue_rows_count' => $this->importRowIssueService->constrainActionRequired(...),
                'importRows as advisory_rows_count' => $this->importRowIssueService->constrainAdvisory(...),
            ])
            ->latest()
            ->paginate(15)
            ->withQueryString();
    }
}
