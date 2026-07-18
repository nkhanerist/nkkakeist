<?php

namespace App\Services\Dashboard;

use App\Models\Account;
use App\Models\User;
use App\Services\Accounts\AccountBalanceCalculatorService;
use Carbon\CarbonImmutable;

class AccountBalanceSummaryService
{
    public function __construct(
        private readonly AccountBalanceCalculatorService $accountBalanceCalculatorService,
    ) {}

    /**
     * @return array<int, array{
     *     id: int,
     *     name: string,
     *     type: string,
     *     currency: string,
     *     initial_balance: string,
     *     current_balance: string,
     *     is_active: bool
     * }>
     */
    public function handle(User $user, CarbonImmutable $month): array
    {
        $endDate = $month->endOfMonth()->toDateString();

        return $user->accounts()
            ->orderBy('display_order')
            ->orderBy('id')
            ->get()
            ->filter(fn (Account $account): bool => $this->shouldIncludeAccount($user, $account, $endDate))
            ->map(function (Account $account) use ($endDate): array {
                return [
                    'id' => $account->id,
                    'name' => $account->name,
                    'type' => $account->type,
                    'currency' => $account->currency,
                    'initial_balance' => (string) $account->initial_balance,
                    'current_balance' => $this->accountBalanceCalculatorService->calculate($account, $endDate),
                    'is_active' => $account->is_active,
                ];
            })
            ->values()
            ->all();
    }

    private function shouldIncludeAccount(User $user, Account $account, string $endDate): bool
    {
        if (
            $account->opening_balance_date !== null
            && $account->opening_balance_date->toDateString() > $endDate
        ) {
            return false;
        }

        if ($account->created_at?->toDateString() <= $endDate) {
            return true;
        }

        if ($account->transactions()
            ->whereDate('transaction_date', '<=', $endDate)
            ->where(function ($query): void {
                $query->where('type', 'transfer')
                    ->orWhere(function ($subQuery): void {
                        $subQuery->where('affects_account_balance', true)
                            ->whereIn('type', ['income', 'expense']);
                    });
            })
            ->exists()) {
            return true;
        }

        return $this->hasRelevantIncomingTransfer($user, $account, $endDate);
    }

    private function hasRelevantIncomingTransfer(User $user, Account $account, string $endDate): bool
    {
        return $user->transactions()
            ->where('transfer_account_id', $account->id)
            ->where('type', 'transfer')
            ->whereHas('account', fn ($accountQuery) => $accountQuery->where('currency', $account->currency))
            ->whereDate('transaction_date', '<=', $endDate)
            ->exists();
    }
}
