<?php

namespace App\Services\Dashboard;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class YearlyCategoryExpenseSummaryService
{
    /**
     * @return array<int, array{
     *     currency: string,
     *     items: array<int, array{category_id: int|null, category_name: string, total_amount: string}>
     * }>
     */
    public function handle(User $user, CarbonImmutable $year): array
    {
        $minorUnitExpression = $this->minorUnitExpression('transactions.amount');

        $rows = $user->transactions()
            ->leftJoin('categories', 'categories.id', '=', 'transactions.category_id')
            ->where('transactions.is_calculation_target', true)
            ->where('transactions.type', 'expense')
            ->whereBetween('transactions.transaction_date', [
                $year->startOfYear()->toDateString(),
                $year->endOfYear()->toDateString(),
            ])
            ->groupBy('transactions.currency', 'categories.id', 'categories.name')
            ->orderBy('transactions.currency')
            ->orderByDesc(DB::raw("SUM({$minorUnitExpression})"))
            ->selectRaw("
                transactions.currency as currency,
                categories.id as category_id,
                categories.name as category_name,
                SUM({$minorUnitExpression}) as total_minor_units
            ")
            ->get()
            ->map(fn ($row): array => [
                'currency' => (string) $row->currency,
                'category_id' => $row->category_id === null ? null : (int) $row->category_id,
                'category_name' => $row->category_id === null
                    ? __('dashboard.report.uncategorized')
                    : (string) $row->category_name,
                'total_amount' => $this->formatMinorUnits((int) $row->total_minor_units),
            ]);

        return $rows
            ->groupBy('currency')
            ->map(fn ($items, string $currency): array => [
                'currency' => $currency,
                'items' => $items->map(fn (array $item): array => [
                    'category_id' => $item['category_id'],
                    'category_name' => $item['category_name'],
                    'total_amount' => $item['total_amount'],
                ])->values()->all(),
            ])
            ->values()
            ->all();
    }

    private function formatMinorUnits(int $minorUnits): string
    {
        $isNegative = $minorUnits < 0;
        $absolute = abs($minorUnits);
        $whole = intdiv($absolute, 100);
        $fraction = str_pad((string) ($absolute % 100), 2, '0', STR_PAD_LEFT);

        return sprintf('%s%d.%s', $isNegative ? '-' : '', $whole, $fraction);
    }

    private function minorUnitExpression(string $column): string
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'mysql') {
            return sprintf('CAST(ROUND(%s * 100, 0) AS SIGNED)', $column);
        }

        return sprintf('CAST(ROUND(%s * 100, 0) AS INTEGER)', $column);
    }
}
