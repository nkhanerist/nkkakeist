<?php

namespace App\Http\Controllers;

use App\Http\Requests\Accounts\StoreAccountReconciliationRequest;
use App\Models\Account;
use App\Models\AccountSnapshot;
use App\Services\Accounts\AccountBalanceReconciliationService;
use App\Services\Accounts\AccountOptionsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AccountReconciliationController extends Controller
{
    public function __construct(
        private readonly AccountBalanceReconciliationService $reconciliationService,
        private readonly AccountOptionsService $accountOptionsService,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Account::class);

        $validated = $request->validate([
            'balance_date' => ['nullable', 'date', 'before_or_equal:today'],
        ]);
        $balanceDate = (string) ($validated['balance_date'] ?? now()->toDateString());
        $typeLabels = $this->accountOptionsService->typeLabels();
        $roleLabels = $this->accountOptionsService->balanceRoleLabels();

        $accounts = $this->reconciliationService
            ->overview($request->user(), $balanceDate)
            ->map(function (array $summary) use ($typeLabels, $roleLabels): array {
                /** @var Account $account */
                $account = $summary['account'];
                /** @var AccountSnapshot|null $latestSnapshot */
                $latestSnapshot = $summary['latest_snapshot'];
                /** @var AccountSnapshot|null $latestOfficialBalance */
                $latestOfficialBalance = $summary['latest_official_balance'];

                return [
                    'id' => $account->id,
                    'name' => $account->name,
                    'type' => $account->type,
                    'type_label' => $typeLabels[$account->type] ?? $account->type,
                    'balance_role' => $account->balance_role,
                    'balance_role_label' => $roleLabels[$account->balance_role] ?? $account->balance_role,
                    'balance_method' => $account->balance_method,
                    'include_in_net_worth' => $account->include_in_net_worth,
                    'currency' => $account->currency,
                    'initial_balance' => (string) $account->initial_balance,
                    'opening_balance_date' => $account->opening_balance_date?->toDateString(),
                    'current_balance' => $summary['current_balance'],
                    'latest_snapshot_date' => $latestSnapshot?->captured_at->toDateString(),
                    'latest_snapshot_balance' => $latestSnapshot !== null
                        ? (string) $latestSnapshot->balance
                        : null,
                    'latest_official_balance_date' => $latestOfficialBalance?->captured_at->toDateString(),
                    'latest_official_balance' => $latestOfficialBalance !== null
                        ? (string) $latestOfficialBalance->balance
                        : null,
                    'latest_official_balance_source' => $latestOfficialBalance?->source_name,
                    'next_payment_amount' => is_string($latestOfficialBalance?->metadata['next_payment_amount'] ?? null)
                        ? $latestOfficialBalance->metadata['next_payment_amount']
                        : null,
                    'next_payment_date' => is_string($latestOfficialBalance?->metadata['next_payment_date'] ?? null)
                        ? $latestOfficialBalance->metadata['next_payment_date']
                        : null,
                ];
            })
            ->values();

        return Inertia::render('Accounts/Reconciliation/Index', [
            'balanceDate' => $balanceDate,
            'reconcilableAccounts' => $accounts
                ->where('balance_method', 'ledger')
                ->whereIn('balance_role', ['asset', 'liability'])
                ->values(),
            'snapshotAccounts' => $accounts
                ->where('balance_method', 'snapshot')
                ->values(),
            'clearingAccounts' => $accounts
                ->where('balance_role', 'clearing')
                ->values(),
        ]);
    }

    public function store(
        StoreAccountReconciliationRequest $request,
        Account $account,
    ): RedirectResponse {
        $result = $this->reconciliationService->reconcile(
            $account,
            $request->validated(),
        );

        return to_route('accounts.reconciliation.index', [
            'balance_date' => $request->validated('balance_date'),
        ])->with('success', trans('accounts.messages.reconciled', [
            'name' => $account->name,
            'difference' => $result['difference'],
            'currency' => $account->currency,
        ]));
    }
}
