<?php

namespace App\Http\Controllers;

use App\Actions\Accounts\DeleteAccountAction;
use App\Actions\Accounts\ListAccountsAction;
use App\Actions\Accounts\StoreAccountAction;
use App\Actions\Accounts\UpdateAccountAction;
use App\Http\Requests\Accounts\StoreAccountRequest;
use App\Http\Requests\Accounts\UpdateAccountRequest;
use App\Models\Account;
use App\Services\Accounts\AccountOptionsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AccountController extends Controller
{
    public function __construct(
        private readonly ListAccountsAction $listAccountsAction,
        private readonly StoreAccountAction $storeAccountAction,
        private readonly UpdateAccountAction $updateAccountAction,
        private readonly DeleteAccountAction $deleteAccountAction,
        private readonly AccountOptionsService $accountOptionsService,
    ) {}

    public function index(): Response
    {
        $this->authorize('viewAny', Account::class);

        $typeLabels = $this->accountOptionsService->typeLabels();
        $balanceRoleLabels = $this->accountOptionsService->balanceRoleLabels();
        $balanceMethodLabels = $this->accountOptionsService->balanceMethodLabels();
        $accounts = $this->listAccountsAction
            ->handle(request()->user())
            ->map(fn (Account $account): array => [
                'id' => $account->id,
                'name' => $account->name,
                'type' => $account->type,
                'type_label' => $typeLabels[$account->type] ?? $account->type,
                'balance_role' => $account->balance_role,
                'balance_role_label' => $balanceRoleLabels[$account->balance_role] ?? $account->balance_role,
                'balance_method' => $account->balance_method,
                'balance_method_label' => $balanceMethodLabels[$account->balance_method] ?? $account->balance_method,
                'include_in_net_worth' => $account->include_in_net_worth,
                'currency' => $account->currency,
                'initial_balance' => $account->initial_balance,
                'opening_balance_date' => $account->opening_balance_date?->toDateString(),
                'display_order' => $account->display_order,
                'is_active' => $account->is_active,
                'note' => $account->note,
            ])
            ->values();

        return Inertia::render('Accounts/Index', [
            'accounts' => $accounts,
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Account::class);

        return Inertia::render('Accounts/Create', [
            'typeOptions' => $this->accountOptionsService->typeOptions(),
            'balanceRoleOptions' => $this->accountOptionsService->balanceRoleOptions(),
            'balanceMethodOptions' => $this->accountOptionsService->balanceMethodOptions(),
        ]);
    }

    public function store(StoreAccountRequest $request): RedirectResponse
    {
        $this->storeAccountAction->handle(
            $request->user(),
            $request->validated(),
        );

        return to_route('accounts.index');
    }

    public function edit(Account $account): Response
    {
        $this->authorize('update', $account);

        return Inertia::render('Accounts/Edit', [
            'account' => [
                'id' => $account->id,
                'name' => $account->name,
                'type' => $account->type,
                'balance_role' => $account->balance_role,
                'balance_method' => $account->balance_method,
                'include_in_net_worth' => $account->include_in_net_worth,
                'currency' => $account->currency,
                'initial_balance' => $account->initial_balance,
                'opening_balance_date' => $account->opening_balance_date?->toDateString(),
                'display_order' => $account->display_order,
                'is_active' => $account->is_active,
                'note' => $account->note,
                'import_aliases' => $account->import_aliases ?? [],
            ],
            'typeOptions' => $this->accountOptionsService->typeOptions(),
            'balanceRoleOptions' => $this->accountOptionsService->balanceRoleOptions(),
            'balanceMethodOptions' => $this->accountOptionsService->balanceMethodOptions(),
        ]);
    }

    public function update(UpdateAccountRequest $request, Account $account): RedirectResponse
    {
        $this->updateAccountAction->handle($account, $request->validated());

        return to_route('accounts.index');
    }

    public function destroy(Account $account): RedirectResponse
    {
        $this->authorize('delete', $account);

        try {
            $this->deleteAccountAction->handle($account);
        } catch (ValidationException $exception) {
            return to_route('accounts.index')->with(
                'error',
                $exception->errors()['account'][0] ?? '口座を削除できませんでした。',
            );
        }

        return to_route('accounts.index');
    }
}
