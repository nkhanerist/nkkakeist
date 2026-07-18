<?php

namespace App\Http\Controllers;

use App\Actions\Securities\BuildSecuritiesAccountDetailAction;
use App\Actions\Securities\BuildSecuritiesOverviewAction;
use App\Models\Account;
use Inertia\Inertia;
use Inertia\Response;

class SecuritiesController extends Controller
{
    public function __construct(
        private readonly BuildSecuritiesOverviewAction $buildSecuritiesOverviewAction,
        private readonly BuildSecuritiesAccountDetailAction $buildSecuritiesAccountDetailAction,
    ) {}

    public function index(): Response
    {
        return Inertia::render(
            'Securities/Index',
            $this->buildSecuritiesOverviewAction->handle(
                request()->user(),
                (string) request()->query('period', '90d'),
            ),
        );
    }

    public function show(Account $account): Response
    {
        $this->authorize('view', $account);
        abort_unless($account->type === 'securities', 404);

        return Inertia::render(
            'Securities/Show',
            $this->buildSecuritiesAccountDetailAction->handle(
                request()->user(),
                $account,
                (string) request()->query('period', '90d'),
                request()->filled('position')
                    ? (string) request()->query('position')
                    : null,
            ),
        );
    }
}
