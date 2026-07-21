<?php

namespace App\Actions\Transactions;

use App\Models\Account;
use App\Models\Category;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class ListTransactionsAction
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function handle(User $user, array $filters): LengthAwarePaginator
    {
        $query = $user->transactions()
            ->getQuery()
            ->with(['account', 'transferAccount', 'category', 'subcategory'])
            ->when(
                filled($filters['date_from'] ?? null),
                fn ($query) => $query->whereDate('transaction_date', '>=', $filters['date_from']),
            )
            ->when(
                filled($filters['date_to'] ?? null),
                fn ($query) => $query->whereDate('transaction_date', '<=', $filters['date_to']),
            )
            ->when(
                filled($filters['account_id'] ?? null),
                fn ($query) => $query->where(function ($accountQuery) use ($filters): void {
                    $accountQuery
                        ->where('account_id', $filters['account_id'])
                        ->orWhere('transfer_account_id', $filters['account_id']);
                }),
            )
            ->when(
                filled($filters['category_id'] ?? null),
                fn ($query) => $query->where('category_id', $filters['category_id']),
            )
            ->when(
                ($filters['category_state'] ?? 'all') === 'categorized',
                fn ($query) => $query->whereNotNull('category_id'),
            )
            ->when(
                ($filters['category_state'] ?? 'all') === 'uncategorized',
                fn ($query) => $query->whereNull('category_id'),
            )
            ->when(
                filled($filters['currency'] ?? null),
                fn ($query) => $query->where('currency', $filters['currency']),
            )
            ->when(
                filled($filters['type'] ?? null),
                fn ($query) => $query->where('type', $filters['type']),
            )
            ->when(
                isset($filters['is_confirmed']) && $filters['is_confirmed'] !== '',
                fn ($query) => $query->where('is_confirmed', $filters['is_confirmed'] === '1'),
            )
            ->when(
                ($filters['calculation_target'] ?? 'all') === 'included',
                fn ($query) => $query->where('is_calculation_target', true),
            )
            ->when(
                ($filters['calculation_target'] ?? 'all') === 'excluded',
                fn ($query) => $query->where('is_calculation_target', false),
            )
            ->when(
                filled($filters['keyword'] ?? null),
                fn ($query) => $query->where(function ($keywordQuery) use ($filters): void {
                    $keywordQuery
                        ->where('merchant_name', 'like', '%'.$filters['keyword'].'%')
                        ->orWhere('description', 'like', '%'.$filters['keyword'].'%')
                        ->orWhere('memo', 'like', '%'.$filters['keyword'].'%')
                        ->orWhere('payment_method_label', 'like', '%'.$filters['keyword'].'%');
                }),
            );

        $this->applySort(
            $query,
            (string) ($filters['sort'] ?? 'date'),
            (string) ($filters['direction'] ?? 'desc'),
        );

        return $query
            ->paginate(15)
            ->withQueryString();
    }

    private function applySort(Builder $query, string $sort, string $direction): void
    {
        $sort = in_array($sort, ['date', 'amount', 'account', 'category', 'summary'], true)
            ? $sort
            : 'date';
        $direction = $direction === 'asc' ? 'asc' : 'desc';

        if ($sort === 'date') {
            $query
                ->orderBy('transaction_date', $direction)
                ->orderBy('id', $direction);

            return;
        }

        match ($sort) {
            'amount' => $query
                ->orderBy('currency')
                ->orderBy('amount', $direction),
            'account' => $query->orderBy(
                Account::query()
                    ->select('name')
                    ->whereColumn('accounts.id', 'transactions.account_id'),
                $direction,
            ),
            'category' => $query
                ->orderByRaw('category_id IS NULL')
                ->orderBy(
                    Category::query()
                        ->select('name')
                        ->whereColumn('categories.id', 'transactions.category_id'),
                    $direction,
                ),
            'summary' => $query->orderByRaw(
                "COALESCE(merchant_name, description, memo, '') {$direction}",
            ),
            default => null,
        };

        $query
            ->orderByDesc('transaction_date')
            ->orderByDesc('id');
    }
}
