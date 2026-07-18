<?php

namespace App\Http\Controllers;

use App\Actions\Dashboard\BuildDashboardAction;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        private readonly BuildDashboardAction $buildDashboardAction,
    ) {}

    public function index(): Response
    {
        $dashboard = $this->buildDashboardAction->handle(request()->user(), request()->query());

        return Inertia::render('Dashboard/Index', $dashboard);
    }
}
