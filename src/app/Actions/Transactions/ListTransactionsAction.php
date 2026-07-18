<?php

namespace App\Actions\Transactions;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ListTransactionsAction
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function handle(User $user, array $filters): LengthAwarePaginator
    {
        return $user->transactions()
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
                fn ($query) => $query->where('account_id', $filters['account_id']),
            )
            ->when(
                filled($filters['category_id'] ?? null),
                fn ($query) => $query->where('category_id', $filters['category_id']),
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
            )
            ->orderByDesc('transaction_date')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();
    }
}
