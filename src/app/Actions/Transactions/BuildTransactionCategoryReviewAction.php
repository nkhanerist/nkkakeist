<?php

namespace App\Actions\Transactions;

use App\Models\User;
use App\Services\Diagnostics\SuggestTransactionCategoriesService;

class BuildTransactionCategoryReviewAction
{
    private const MAX_VISIBLE_ITEMS = 50;

    public function __construct(
        private readonly SuggestTransactionCategoriesService $suggestTransactionCategoriesService,
    ) {}

    /**
     * @param  array{status:string, type:string}  $filters
     * @return array<string, mixed>
     */
    public function handle(User $user, array $filters): array
    {
        $suggestions = $this->suggestTransactionCategoriesService->handle(
            userId: $user->id,
            type: $filters['type'],
            minConfidence: 0,
            limit: PHP_INT_MAX,
            mode: 'all',
        );

        $highConfidence = $suggestions->filter(
            fn (array $suggestion): bool => $suggestion['confidence'] >= 90,
        );
        $manualReview = $suggestions->filter(
            fn (array $suggestion): bool => $suggestion['confidence'] < 90,
        );

        $filtered = match ($filters['status']) {
            'manual' => $manualReview,
            'all' => $suggestions,
            default => $highConfidence,
        };

        return [
            'items' => $filtered
                ->take(self::MAX_VISIBLE_ITEMS)
                ->values()
                ->all(),
            'summary' => [
                'total' => $suggestions->count(),
                'high_confidence' => $highConfidence->count(),
                'manual_review' => $manualReview->count(),
                'displayed' => min($filtered->count(), self::MAX_VISIBLE_ITEMS),
            ],
            'has_more' => $filtered->count() > self::MAX_VISIBLE_ITEMS,
        ];
    }
}
