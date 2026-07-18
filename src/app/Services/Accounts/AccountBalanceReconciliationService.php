<?php

namespace App\Services\Accounts;

use App\Models\Account;
use App\Models\AccountSnapshot;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AccountBalanceReconciliationService
{
    public function __construct(
        private readonly AccountBalanceCalculatorService $balanceCalculator,
    ) {}

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function overview(User $user, string $balanceDate): Collection
    {
        return Account::query()
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->orderBy('display_order')
            ->orderBy('id')
            ->get()
            ->map(function (Account $account) use ($balanceDate): array {
                $latestSnapshot = $account->snapshots()
                    ->where('purpose', $account->balance_method === 'snapshot' ? 'valuation' : 'reconciliation')
                    ->whereDate('captured_at', '<=', $balanceDate)
                    ->orderByDesc('captured_at')
                    ->orderByDesc('id')
                    ->first();
                $latestOfficialBalance = $account->snapshots()
                    ->where('purpose', 'official_balance')
                    ->whereDate('captured_at', '<=', $balanceDate)
                    ->orderByDesc('captured_at')
                    ->orderByDesc('id')
                    ->first();

                return [
                    'account' => $account,
                    'current_balance' => $this->balanceCalculator->calculate($account, $balanceDate),
                    'latest_snapshot' => $latestSnapshot,
                    'latest_official_balance' => $latestOfficialBalance,
                ];
            });
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array{difference: string, initial_balance: string, opening_balance_date: string}
     */
    public function reconcile(Account $account, array $attributes): array
    {
        if ($account->balance_method !== 'ledger' || $account->balance_role === 'clearing') {
            throw ValidationException::withMessages([
                'actual_balance' => 'この口座は期首残高の照合対象ではありません。',
            ]);
        }

        $balanceDate = (string) $attributes['balance_date'];
        $actualBalance = $this->normalizeAmount((string) $attributes['actual_balance']);
        $calculatedBalance = $this->balanceCalculator->calculate($account, $balanceDate);
        $difference = $this->balanceCalculator->subtract($actualBalance, $calculatedBalance);
        $previousInitialBalance = (string) $account->initial_balance;
        $initialBalance = $this->balanceCalculator->add($previousInitialBalance, $difference);
        $openingBalanceDate = $account->opening_balance_date?->toDateString()
            ?? $this->firstBalanceTransactionDate($account, $balanceDate)
            ?? $balanceDate;

        DB::transaction(function () use (
            $account,
            $attributes,
            $balanceDate,
            $actualBalance,
            $calculatedBalance,
            $difference,
            $previousInitialBalance,
            $initialBalance,
            $openingBalanceDate,
        ): void {
            $account->update([
                'initial_balance' => $initialBalance,
                'opening_balance_date' => $openingBalanceDate,
            ]);

            $capturedAt = CarbonImmutable::parse($balanceDate, config('app.timezone'))->endOfDay();
            $snapshot = $account->snapshots()
                ->where('purpose', 'reconciliation')
                ->whereNull('import_id')
                ->whereDate('captured_at', $balanceDate)
                ->first();

            $values = [
                'user_id' => $account->user_id,
                'captured_at' => $capturedAt,
                'purpose' => 'reconciliation',
                'balance' => $actualBalance,
                'source_name' => $attributes['source_name'],
                'note' => $attributes['note'],
                'metadata' => [
                    'calculated_balance_before' => $calculatedBalance,
                    'difference' => $difference,
                    'previous_initial_balance' => $previousInitialBalance,
                    'updated_initial_balance' => $initialBalance,
                    'opening_balance_date' => $openingBalanceDate,
                ],
            ];

            if ($snapshot instanceof AccountSnapshot) {
                $snapshot->update($values);
            } else {
                $account->snapshots()->create($values);
            }
        });

        return [
            'difference' => $difference,
            'initial_balance' => $initialBalance,
            'opening_balance_date' => $openingBalanceDate,
        ];
    }

    private function firstBalanceTransactionDate(Account $account, string $endDate): ?string
    {
        $outgoing = $account->transactions()
            ->where('affects_account_balance', true)
            ->whereDate('transaction_date', '<=', $endDate)
            ->where(function ($query) use ($account): void {
                $query->where('type', 'transfer')
                    ->orWhere('currency', $account->currency);
            })
            ->min('transaction_date');

        $incoming = $account->incomingTransferTransactions()
            ->where('affects_account_balance', true)
            ->where('type', 'transfer')
            ->whereDate('transaction_date', '<=', $endDate)
            ->whereHas('account', fn ($query) => $query->where('currency', $account->currency))
            ->min('transaction_date');

        $dates = array_values(array_filter([(string) $outgoing, (string) $incoming]));

        return $dates === [] ? null : min($dates);
    }

    private function normalizeAmount(string $amount): string
    {
        return number_format((float) $amount, 2, '.', '');
    }
}
