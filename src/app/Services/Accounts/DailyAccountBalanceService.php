<?php

namespace App\Services\Accounts;

use App\Models\Account;
use App\Models\AccountSnapshot;
use App\Models\Transaction;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class DailyAccountBalanceService
{
    /**
     * @param  Collection<int, Account>  $accounts
     * @return array<int, array{date: string, balances: array<int, string>}>
     */
    public function handle(
        User $user,
        Collection $accounts,
        CarbonImmutable $startDate,
        CarbonImmutable $endDate,
    ): array {
        if ($accounts->isEmpty() || $startDate->isAfter($endDate)) {
            return [];
        }

        $accountIds = $accounts->pluck('id')->all();
        $accountCurrencies = $accounts->pluck('currency', 'id');
        $dailyDeltas = $this->dailyDeltas($user, $accountIds, $accountCurrencies, $endDate);
        $valuationSnapshots = $this->valuationSnapshots($user, $accountIds, $endDate);
        $dates = $this->dates($startDate, $endDate);
        $rows = array_map(
            static fn (string $date): array => ['date' => $date, 'balances' => []],
            $dates,
        );

        foreach ($accounts as $account) {
            $accountDeltas = $dailyDeltas[$account->id] ?? [];
            ksort($accountDeltas);
            [$deltaDates, $cumulativeDeltas] = $this->cumulativeDeltas($accountDeltas);
            $snapshots = $valuationSnapshots[$account->id] ?? collect();

            foreach ($dates as $index => $date) {
                $rows[$index]['balances'][$account->id] = $this->balanceOnDate(
                    $account,
                    $date,
                    $deltaDates,
                    $cumulativeDeltas,
                    $snapshots,
                );
            }
        }

        return $rows;
    }

    /**
     * @param  array<int, int>  $accountIds
     * @param  Collection<int, string>  $accountCurrencies
     * @return array<int, array<string, int>>
     */
    private function dailyDeltas(
        User $user,
        array $accountIds,
        Collection $accountCurrencies,
        CarbonImmutable $endDate,
    ): array {
        $transactions = Transaction::query()
            ->where('user_id', $user->id)
            ->where('affects_account_balance', true)
            ->whereDate('transaction_date', '<=', $endDate->toDateString())
            ->where(function ($query) use ($accountIds): void {
                $query->whereIn('account_id', $accountIds)
                    ->orWhereIn('transfer_account_id', $accountIds);
            })
            ->with('account:id,currency')
            ->get([
                'id',
                'account_id',
                'transfer_account_id',
                'transaction_date',
                'type',
                'amount',
                'currency',
            ]);

        $deltas = [];

        foreach ($transactions as $transaction) {
            $date = $transaction->transaction_date->toDateString();
            $amount = $this->toMinorUnits((string) $transaction->amount);

            if (in_array($transaction->account_id, $accountIds, true)) {
                $sourceCurrency = $accountCurrencies->get($transaction->account_id);
                $sourceDelta = match ($transaction->type) {
                    'income' => $transaction->currency === $sourceCurrency ? $amount : 0,
                    'expense' => $transaction->currency === $sourceCurrency ? -$amount : 0,
                    'transfer' => -$amount,
                    default => 0,
                };
                $deltas[$transaction->account_id][$date] =
                    ($deltas[$transaction->account_id][$date] ?? 0) + $sourceDelta;
            }

            if (
                $transaction->type === 'transfer'
                && $transaction->transfer_account_id !== null
                && in_array($transaction->transfer_account_id, $accountIds, true)
                && $transaction->account?->currency
                    === $accountCurrencies->get($transaction->transfer_account_id)
            ) {
                $deltas[$transaction->transfer_account_id][$date] =
                    ($deltas[$transaction->transfer_account_id][$date] ?? 0) + $amount;
            }
        }

        return $deltas;
    }

    /**
     * @param  array<int, int>  $accountIds
     * @return Collection<int, Collection<int, AccountSnapshot>>
     */
    private function valuationSnapshots(
        User $user,
        array $accountIds,
        CarbonImmutable $endDate,
    ): Collection {
        return AccountSnapshot::query()
            ->where('user_id', $user->id)
            ->whereIn('account_id', $accountIds)
            ->where('purpose', 'valuation')
            ->whereDate('captured_at', '<=', $endDate->toDateString())
            ->orderBy('captured_at')
            ->orderBy('id')
            ->get(['id', 'account_id', 'captured_at', 'balance'])
            ->groupBy('account_id');
    }

    /**
     * @return array<int, string>
     */
    private function dates(CarbonImmutable $startDate, CarbonImmutable $endDate): array
    {
        $dates = [];

        for ($date = $startDate; $date->lte($endDate); $date = $date->addDay()) {
            $dates[] = $date->toDateString();
        }

        return $dates;
    }

    /**
     * @param  array<string, int>  $deltas
     * @return array{0: array<int, string>, 1: array<int, int>}
     */
    private function cumulativeDeltas(array $deltas): array
    {
        $dates = [];
        $amounts = [];
        $running = 0;

        foreach ($deltas as $date => $amount) {
            $running += $amount;
            $dates[] = $date;
            $amounts[] = $running;
        }

        return [$dates, $amounts];
    }

    /**
     * @param  array<int, string>  $deltaDates
     * @param  array<int, int>  $cumulativeDeltas
     * @param  Collection<int, AccountSnapshot>  $snapshots
     */
    private function balanceOnDate(
        Account $account,
        string $date,
        array $deltaDates,
        array $cumulativeDeltas,
        Collection $snapshots,
    ): string {
        $openingDate = $account->opening_balance_date?->toDateString();

        if ($openingDate !== null && $openingDate > $date) {
            return $this->formatAmount(0);
        }

        $currentDelta = $this->cumulativeAmountAt($deltaDates, $cumulativeDeltas, $date);
        $baseline = $this->toMinorUnits((string) $account->initial_balance);
        $baselineDelta = $openingDate === null
            ? 0
            : $this->cumulativeAmountAt(
                $deltaDates,
                $cumulativeDeltas,
                CarbonImmutable::parse($openingDate)->subDay()->toDateString(),
            );

        if ($account->balance_method === 'snapshot') {
            $snapshot = $snapshots
                ->last(fn (AccountSnapshot $item): bool => $item->captured_at->toDateString() <= $date);

            if ($snapshot instanceof AccountSnapshot) {
                $snapshotDate = $snapshot->captured_at->toDateString();
                $baseline = $this->toMinorUnits((string) $snapshot->balance);
                $baselineDelta = $this->cumulativeAmountAt(
                    $deltaDates,
                    $cumulativeDeltas,
                    $snapshotDate,
                );
            }
        }

        return $this->formatAmount($baseline + $currentDelta - $baselineDelta);
    }

    /**
     * @param  array<int, string>  $dates
     * @param  array<int, int>  $amounts
     */
    private function cumulativeAmountAt(array $dates, array $amounts, string $targetDate): int
    {
        $low = 0;
        $high = count($dates) - 1;
        $resultIndex = -1;

        while ($low <= $high) {
            $middle = intdiv($low + $high, 2);

            if ($dates[$middle] <= $targetDate) {
                $resultIndex = $middle;
                $low = $middle + 1;
            } else {
                $high = $middle - 1;
            }
        }

        return $resultIndex >= 0 ? $amounts[$resultIndex] : 0;
    }

    private function toMinorUnits(string $amount): int
    {
        $normalized = trim($amount);
        $isNegative = str_starts_with($normalized, '-');
        $normalized = ltrim($normalized, '+-');
        [$whole, $fraction] = array_pad(explode('.', $normalized, 2), 2, '0');
        $fraction = substr(str_pad($fraction, 2, '0'), 0, 2);
        $minorUnits = ((int) $whole * 100) + (int) $fraction;

        return $isNegative ? -$minorUnits : $minorUnits;
    }

    private function formatAmount(int $minorUnits): string
    {
        $sign = $minorUnits < 0 ? '-' : '';
        $absolute = abs($minorUnits);

        return sprintf(
            '%s%d.%s',
            $sign,
            intdiv($absolute, 100),
            str_pad((string) ($absolute % 100), 2, '0', STR_PAD_LEFT),
        );
    }
}
