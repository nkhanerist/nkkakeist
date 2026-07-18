<?php

namespace App\Services\Accounts;

use App\Models\Account;

class AccountBalanceCalculatorService
{
    public function calculate(Account $account, string $endDate, ?string $initialBalance = null): string
    {
        $openingBalanceDate = $account->opening_balance_date?->toDateString();

        if ($openingBalanceDate !== null && $openingBalanceDate > $endDate) {
            return $this->formatAmount(0);
        }

        $baseline = $initialBalance ?? (string) $account->initial_balance;
        $startDateInclusive = $openingBalanceDate;
        $startDateExclusive = null;

        if ($account->balance_method === 'snapshot' && $initialBalance === null) {
            $snapshot = $account->snapshots()
                ->where('purpose', 'valuation')
                ->whereDate('captured_at', '<=', $endDate)
                ->orderByDesc('captured_at')
                ->orderByDesc('id')
                ->first();

            if ($snapshot !== null) {
                $baseline = (string) $snapshot->balance;
                $startDateInclusive = null;
                $startDateExclusive = $snapshot->captured_at->toDateString();
            }
        }

        $income = (string) $account->transactions()
            ->where('affects_account_balance', true)
            ->where('currency', $account->currency)
            ->where('type', 'income')
            ->when($startDateInclusive, fn ($query, string $date) => $query
                ->whereDate('transaction_date', '>=', $date))
            ->when($startDateExclusive, fn ($query, string $date) => $query
                ->whereDate('transaction_date', '>', $date))
            ->whereDate('transaction_date', '<=', $endDate)
            ->sum('amount');

        $expense = (string) $account->transactions()
            ->where('affects_account_balance', true)
            ->where('currency', $account->currency)
            ->where('type', 'expense')
            ->when($startDateInclusive, fn ($query, string $date) => $query
                ->whereDate('transaction_date', '>=', $date))
            ->when($startDateExclusive, fn ($query, string $date) => $query
                ->whereDate('transaction_date', '>', $date))
            ->whereDate('transaction_date', '<=', $endDate)
            ->sum('amount');

        $transferOut = (string) $account->transactions()
            ->where('affects_account_balance', true)
            ->where('type', 'transfer')
            ->when($startDateInclusive, fn ($query, string $date) => $query
                ->whereDate('transaction_date', '>=', $date))
            ->when($startDateExclusive, fn ($query, string $date) => $query
                ->whereDate('transaction_date', '>', $date))
            ->whereDate('transaction_date', '<=', $endDate)
            ->sum('amount');

        $transferIn = (string) $account->incomingTransferTransactions()
            ->where('affects_account_balance', true)
            ->where('type', 'transfer')
            ->whereHas('account', fn ($query) => $query->where('currency', $account->currency))
            ->when($startDateInclusive, fn ($query, string $date) => $query
                ->whereDate('transaction_date', '>=', $date))
            ->when($startDateExclusive, fn ($query, string $date) => $query
                ->whereDate('transaction_date', '>', $date))
            ->whereDate('transaction_date', '<=', $endDate)
            ->sum('amount');

        $currentBalance = $this->toMinorUnits($baseline);
        $currentBalance += $this->toMinorUnits($income);
        $currentBalance -= $this->toMinorUnits($expense);
        $currentBalance -= $this->toMinorUnits($transferOut);
        $currentBalance += $this->toMinorUnits($transferIn);

        return $this->formatAmount($currentBalance);
    }

    public function add(string $left, string $right): string
    {
        return $this->formatAmount($this->toMinorUnits($left) + $this->toMinorUnits($right));
    }

    public function subtract(string $left, string $right): string
    {
        return $this->formatAmount($this->toMinorUnits($left) - $this->toMinorUnits($right));
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
        $isNegative = $minorUnits < 0;
        $absolute = abs($minorUnits);
        $whole = intdiv($absolute, 100);
        $fraction = str_pad((string) ($absolute % 100), 2, '0', STR_PAD_LEFT);

        return sprintf('%s%d.%s', $isNegative ? '-' : '', $whole, $fraction);
    }
}
