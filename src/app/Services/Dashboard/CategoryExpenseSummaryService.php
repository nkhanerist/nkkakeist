<?php

namespace App\Services\Dashboard;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class CategoryExpenseSummaryService
{
    /**
     * @return array<int, array{id: int|null, name: string, currency: string, total_amount: string}>
     */
    public function handle(User $user, CarbonImmutable $month): array
    {
        $minorUnitExpression = $this->minorUnitExpression('transactions.amount');

        return $user->transactions()
            ->leftJoin('categories', 'categories.id', '=', 'transactions.category_id')
            ->where('transactions.is_calculation_target', true)
            ->where('transactions.type', 'expense')
            ->whereBetween('transactions.transaction_date', [
                $month->startOfMonth()->toDateString(),
                $month->endOfMonth()->toDateString(),
            ])
            ->groupBy('transactions.currency', 'categories.id', 'categories.name')
            ->orderBy('transactions.currency')
            ->orderByDesc(DB::raw('SUM(transactions.amount)'))
            ->selectRaw("
                transactions.currency as currency,
                categories.id as id,
                COALESCE(categories.name, '未分類') as name,
                SUM({$minorUnitExpression}) as total_minor_units
            ")
            ->get()
            ->map(fn ($row): array => [
                'id' => $row->id === null ? null : (int) $row->id,
                'name' => (string) $row->name,
                'currency' => (string) $row->currency,
                'total_amount' => $this->formatMinorUnits((int) $row->total_minor_units),
            ])
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
