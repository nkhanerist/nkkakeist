<?php

namespace App\Http\Controllers;

use App\Actions\Accounts\DeleteAccountSnapshotAction;
use App\Actions\Accounts\StoreAccountSnapshotAction;
use App\Actions\Accounts\UpdateAccountSnapshotAction;
use App\Http\Requests\Accounts\StoreAccountSnapshotRequest;
use App\Http\Requests\Accounts\UpdateAccountSnapshotRequest;
use App\Models\Account;
use App\Models\AccountSnapshot;
use App\Services\Accounts\AccountBalanceCalculatorService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class AccountSnapshotController extends Controller
{
    public function __construct(
        private readonly StoreAccountSnapshotAction $storeAccountSnapshotAction,
        private readonly UpdateAccountSnapshotAction $updateAccountSnapshotAction,
        private readonly DeleteAccountSnapshotAction $deleteAccountSnapshotAction,
        private readonly AccountBalanceCalculatorService $accountBalanceCalculatorService,
    ) {}

    public function index(Account $account): Response
    {
        $this->authorize('view', $account);

        $snapshots = $account->snapshots()
            ->where('purpose', 'valuation')
            ->orderByDesc('captured_at')
            ->orderByDesc('id')
            ->get();

        return Inertia::render('Accounts/Snapshots/Index', [
            'account' => [
                'id' => $account->id,
                'name' => $account->name,
                'currency' => $account->currency,
                'balance_method' => $account->balance_method,
                'has_valuation_snapshot' => $snapshots->isNotEmpty(),
                'current_balance' => $this->accountBalanceCalculatorService->calculate(
                    $account,
                    now()->toDateString(),
                ),
            ],
            'today' => now()->toDateString(),
            'snapshots' => $snapshots->map(fn (AccountSnapshot $snapshot): array => [
                'id' => $snapshot->id,
                'balance_date' => $snapshot->captured_at->toDateString(),
                'balance' => (string) $snapshot->balance,
                'source_name' => $snapshot->source_name,
                'note' => $snapshot->note,
            ]),
        ]);
    }

    public function store(StoreAccountSnapshotRequest $request, Account $account): RedirectResponse
    {
        $this->storeAccountSnapshotAction->handle(
            $account,
            $request->validated(),
        );

        return to_route('accounts.snapshots.index', $account)
            ->with('success', trans('accounts.messages.valuation_recorded'));
    }

    public function update(
        UpdateAccountSnapshotRequest $request,
        Account $account,
        AccountSnapshot $accountSnapshot,
    ): RedirectResponse {
        $this->updateAccountSnapshotAction->handle(
            $accountSnapshot,
            $request->validated(),
        );

        return to_route('accounts.snapshots.index', $account)
            ->with('success', trans('accounts.messages.valuation_updated'));
    }

    public function destroy(
        Account $account,
        AccountSnapshot $accountSnapshot,
    ): RedirectResponse {
        $this->authorize('update', $account);
        abort_unless($accountSnapshot->account_id === $account->id, 404);

        $this->deleteAccountSnapshotAction->handle($accountSnapshot);

        return to_route('accounts.snapshots.index', $account)
            ->with('success', trans('accounts.messages.valuation_deleted'));
    }
}
