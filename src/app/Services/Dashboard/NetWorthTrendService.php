<?php

namespace App\Services\Dashboard;

use App\Models\AccountSnapshot;
use App\Models\User;
use App\Services\Accounts\DailyAccountBalanceService;
use Carbon\CarbonImmutable;

class NetWorthTrendService
{
    public function __construct(
        private readonly DailyAccountBalanceService $dailyAccountBalanceService,
    ) {}

    /**
     * @return array<int, array{
     *     currency: string,
     *     points: array<int, array{date: string, assets: string, liabilities: string, net_worth: string}>
     * }>
     */
    public function handle(
        User $user,
        CarbonImmutable $startDate,
        CarbonImmutable $endDate,
    ): array {
        $today = CarbonImmutable::today(config('app.timezone'));
        $effectiveEndDate = $endDate->min($today);

        if ($startDate->isAfter($effectiveEndDate)) {
            return [];
        }

        $accounts = $user->accounts()
            ->where('include_in_net_worth', true)
            ->whereIn('balance_role', ['asset', 'liability'])
            ->orderBy('display_order')
            ->orderBy('id')
            ->get();

        if ($accounts->isEmpty()) {
            return [];
        }

        $snapshotDates = AccountSnapshot::query()
            ->where('user_id', $user->id)
            ->whereIn('account_id', $accounts->pluck('id'))
            ->where(function ($query): void {
                $query->where('purpose', 'valuation')
                    ->orWhereHas('import', fn ($importQuery) => $importQuery
                        ->where('source_name', 'balance_snapshot'));
            })
            ->whereDate('captured_at', '>=', $startDate->toDateString())
            ->whereDate('captured_at', '<=', $effectiveEndDate->toDateString())
            ->get(['captured_at'])
            ->map(fn (AccountSnapshot $snapshot): string => $snapshot->captured_at->toDateString())
            ->unique()
            ->values();

        if ($snapshotDates->isEmpty()) {
            return [];
        }

        $dailyBalances = $this->dailyAccountBalanceService->handle(
            $user,
            $accounts,
            $startDate,
            $effectiveEndDate,
        );
        $dailyBalances = array_values(array_filter(
            $dailyBalances,
            fn (array $item): bool => $snapshotDates->contains($item['date']),
        ));
        $officialBalances = AccountSnapshot::query()
            ->where('user_id', $user->id)
            ->whereIn('account_id', $accounts
                ->whereIn('type', ['bank', 'credit_card'])
                ->pluck('id'))
            ->where('purpose', 'official_balance')
            ->whereDate('captured_at', '>=', $startDate->toDateString())
            ->whereDate('captured_at', '<=', $effectiveEndDate->toDateString())
            ->orderBy('captured_at')
            ->orderBy('id')
            ->get(['account_id', 'captured_at', 'balance'])
            ->keyBy(fn (AccountSnapshot $snapshot): string => sprintf(
                '%d:%s',
                $snapshot->account_id,
                $snapshot->captured_at->toDateString(),
            ));
        $groups = [];

        foreach ($accounts->groupBy('currency') as $currency => $currencyAccounts) {
            $points = [];

            foreach ($dailyBalances as $dailyBalance) {
                $assetTotal = 0;
                $liabilityTotal = 0;

                foreach ($currencyAccounts as $account) {
                    $officialBalance = $officialBalances->get(sprintf(
                        '%d:%s',
                        $account->id,
                        $dailyBalance['date'],
                    ));
                    $balance = $this->toMinorUnits(
                        $officialBalance instanceof AccountSnapshot
                            ? (string) $officialBalance->balance
                            : ($dailyBalance['balances'][$account->id] ?? '0.00'),
                    );

                    if ($account->balance_role === 'asset') {
                        $assetTotal += $balance;
                    } else {
                        $liabilityTotal += max(0, -$balance);
                    }
                }

                $points[] = [
                    'date' => $dailyBalance['date'],
                    'assets' => $this->formatAmount($assetTotal),
                    'liabilities' => $this->formatAmount($liabilityTotal),
                    'net_worth' => $this->formatAmount($assetTotal - $liabilityTotal),
                ];
            }

            $groups[] = [
                'currency' => (string) $currency,
                'points' => $points,
            ];
        }

        return $groups;
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
