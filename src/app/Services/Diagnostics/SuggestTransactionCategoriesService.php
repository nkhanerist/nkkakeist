<?php

namespace App\Services\Diagnostics;

use App\Models\Category;
use App\Models\ClassificationRule;
use App\Models\Subcategory;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class SuggestTransactionCategoriesService
{
    /**
     * @return Collection<int, array{
     *     transaction_id:int,
     *     user_id:int,
     *     type:string,
     *     transaction_date:?string,
     *     amount:string,
     *     currency:string,
     *     account_name:?string,
     *     merchant_name:?string,
     *     description:?string,
     *     current_category:?string,
     *     current_subcategory:?string,
     *     suggested_category_id:?int,
     *     suggested_category:?string,
     *     suggested_subcategory_id:?int,
     *     suggested_subcategory:?string,
     *     confidence:int,
     *     reason:string,
     *     reference_count:int,
     *     reference_transaction_id:?int,
     *     matched_classification_rule_id:?int
     * }>
     */
    public function handle(
        ?int $userId = null,
        ?int $year = null,
        string $type = 'all',
        int $minConfidence = 70,
        int $limit = 50,
        string $mode = 'suggested',
    ): Collection {
        $rulesByUser = $this->rulesByUser($userId);
        $targetTransactions = $this->targetQuery($userId, $year, $type)->get();
        $historyByMerchant = $this->historyByMerchant($targetTransactions);

        return $targetTransactions
            ->map(fn (Transaction $transaction): ?array => $this->categoryResultForTransaction(
                $transaction,
                $rulesByUser,
                $historyByMerchant,
                $minConfidence,
                $mode,
            ))
            ->filter()
            ->sortByDesc(fn (array $suggestion): int => $suggestion['confidence'])
            ->values()
            ->take($limit);
    }

    /**
     * @return Collection<int|string, Collection<int, ClassificationRule>>
     */
    private function rulesByUser(?int $userId): Collection
    {
        return ClassificationRule::query()
            ->with(['category', 'subcategory'])
            ->where('is_active', true)
            ->when($userId !== null, fn ($query) => $query->where('user_id', $userId))
            ->orderBy('user_id')
            ->orderBy('priority')
            ->orderBy('id')
            ->get()
            ->groupBy('user_id');
    }

    private function targetQuery(?int $userId, ?int $year, string $type): Builder
    {
        return Transaction::query()
            ->with(['account', 'category', 'subcategory'])
            ->whereIn('type', $type === 'all' ? ['income', 'expense'] : [$type])
            ->whereNull('category_id')
            ->when($userId !== null, fn ($query) => $query->where('user_id', $userId))
            ->when($year !== null, fn ($query) => $query->whereYear('transaction_date', $year))
            ->orderByDesc('transaction_date')
            ->orderByDesc('id');
    }

    /**
     * @param  Collection<int, Transaction>  $targetTransactions
     * @return Collection<string, Collection<int, Transaction>>
     */
    private function historyByMerchant(Collection $targetTransactions): Collection
    {
        if ($targetTransactions->isEmpty()) {
            return collect();
        }

        $userIds = $targetTransactions->pluck('user_id')->unique()->values();
        $types = $targetTransactions->pluck('type')->unique()->values();

        return Transaction::query()
            ->with(['category', 'subcategory'])
            ->whereIn('user_id', $userIds)
            ->whereIn('type', $types)
            ->whereNotNull('category_id')
            ->whereNotNull('merchant_name')
            ->get()
            ->filter(fn (Transaction $transaction): bool => $this->normalize((string) $transaction->merchant_name) !== '')
            ->groupBy(fn (Transaction $transaction): string => $this->historyKey(
                $transaction->user_id,
                $transaction->type,
                (string) $transaction->merchant_name,
            ));
    }

    /**
     * @param  Collection<int|string, Collection<int, ClassificationRule>>  $rulesByUser
     * @return array<string, mixed>|null
     */
    private function categoryResultForTransaction(
        Transaction $transaction,
        Collection $rulesByUser,
        Collection $historyByMerchant,
        int $minConfidence,
        string $mode,
    ): ?array {
        $suggestion = $this->suggestForTransaction(
            $transaction,
            $rulesByUser,
            $historyByMerchant,
            $minConfidence,
        );

        return match ($mode) {
            'suggested' => $suggestion,
            'missing' => $suggestion === null ? $this->missingSuggestion($transaction) : null,
            'all' => $suggestion ?? $this->missingSuggestion($transaction),
            default => null,
        };
    }

    /**
     * @param  Collection<int|string, Collection<int, ClassificationRule>>  $rulesByUser
     * @param  Collection<string, Collection<int, Transaction>>  $historyByMerchant
     * @return array<string, mixed>|null
     */
    private function suggestForTransaction(
        Transaction $transaction,
        Collection $rulesByUser,
        Collection $historyByMerchant,
        int $minConfidence,
    ): ?array {
        $ruleSuggestion = $this->suggestByRule($transaction, $rulesByUser->get($transaction->user_id, collect()));

        if ($ruleSuggestion !== null && $ruleSuggestion['confidence'] >= $minConfidence) {
            return [
                ...$this->transactionDetails($transaction),
                ...$ruleSuggestion,
            ];
        }

        $historySuggestion = $this->suggestByHistory($transaction, $historyByMerchant, $minConfidence);

        if ($historySuggestion !== null && $historySuggestion['confidence'] >= $minConfidence) {
            return [
                ...$this->transactionDetails($transaction),
                ...$historySuggestion,
            ];
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function missingSuggestion(Transaction $transaction): array
    {
        return [
            ...$this->transactionDetails($transaction),
            'suggested_category_id' => null,
            'suggested_category' => null,
            'suggested_subcategory_id' => null,
            'suggested_subcategory' => null,
            'confidence' => 0,
            'reason' => trans('transactions.category_review.reasons.none'),
            'reference_count' => 0,
            'reference_transaction_id' => null,
            'matched_classification_rule_id' => null,
        ];
    }

    /**
     * @param  Collection<int, ClassificationRule>  $classificationRules
     * @return array<string, mixed>|null
     */
    private function suggestByRule(Transaction $transaction, Collection $classificationRules): ?array
    {
        foreach ($classificationRules as $classificationRule) {
            if (! $this->matchesRule($classificationRule, $transaction)) {
                continue;
            }

            if (! $this->categoryMatchesType($classificationRule->category, $transaction->type)) {
                continue;
            }

            if (! $this->subcategoryMatchesCategory($classificationRule->subcategory, $classificationRule->category)) {
                continue;
            }

            if ($classificationRule->category === null) {
                continue;
            }

            return [
                'suggested_category_id' => $classificationRule->category->id,
                'suggested_category' => $classificationRule->category->name,
                'suggested_subcategory_id' => $classificationRule->subcategory?->id,
                'suggested_subcategory' => $classificationRule->subcategory?->name,
                'confidence' => 100,
                'reason' => trans('transactions.category_review.reasons.rule_match', [
                    'id' => $classificationRule->id,
                ]),
                'reference_count' => 0,
                'reference_transaction_id' => null,
                'matched_classification_rule_id' => $classificationRule->id,
            ];
        }

        return null;
    }

    /**
     * @param  Collection<string, Collection<int, Transaction>>  $historyByMerchant
     * @return array<string, mixed>|null
     */
    private function suggestByHistory(
        Transaction $transaction,
        Collection $historyByMerchant,
        int $minConfidence,
    ): ?array {
        $merchantKey = $this->normalize((string) $transaction->merchant_name);

        if ($merchantKey === '') {
            return null;
        }

        /** @var Collection<int, Transaction> $sameMerchantTransactions */
        $sameMerchantTransactions = $historyByMerchant->get(
            $this->historyKey($transaction->user_id, $transaction->type, $merchantKey),
            collect(),
        );
        $sameAccountTransactions = $sameMerchantTransactions->where('account_id', $transaction->account_id);

        $sameAccountSuggestion = $this->dominantHistorySuggestion(
            $sameAccountTransactions,
            $sameAccountTransactions->count() >= 2 ? 90 : 80,
            trans('transactions.category_review.reasons.same_merchant_account'),
        );

        if ($sameAccountSuggestion !== null && $sameAccountSuggestion['confidence'] >= $minConfidence) {
            return $sameAccountSuggestion;
        }

        return $this->dominantHistorySuggestion(
            $sameMerchantTransactions,
            85,
            trans('transactions.category_review.reasons.same_merchant'),
        );
    }

    private function historyKey(int $userId, string $type, string $merchantName): string
    {
        return json_encode(
            [$userId, $type, $this->normalize($merchantName)],
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE,
        );
    }

    /**
     * @param  Collection<int, Transaction>  $transactions
     * @return array<string, mixed>|null
     */
    private function dominantHistorySuggestion(Collection $transactions, int $confidence, string $reason): ?array
    {
        if ($transactions->isEmpty()) {
            return null;
        }

        $groups = $transactions
            ->filter(fn (Transaction $transaction): bool => $this->categoryMatchesType($transaction->category, $transaction->type))
            ->groupBy(fn (Transaction $transaction): string => $transaction->category_id.'|'.($transaction->subcategory_id ?? ''))
            ->map(fn (Collection $group): array => [
                'count' => $group->count(),
                'latest' => $group->sortByDesc('transaction_date')->first(),
            ])
            ->sortByDesc('count');

        if ($groups->isEmpty()) {
            return null;
        }

        /** @var array{count:int, latest:Transaction} $top */
        $top = $groups->first();
        $total = $transactions->count();

        if (($top['count'] / $total) < 0.7) {
            return null;
        }

        $referenceTransaction = $top['latest'];

        return [
            'suggested_category_id' => $referenceTransaction->category?->id,
            'suggested_category' => $referenceTransaction->category?->name,
            'suggested_subcategory_id' => $referenceTransaction->subcategory?->id,
            'suggested_subcategory' => $referenceTransaction->subcategory?->name,
            'confidence' => $confidence,
            'reason' => $reason,
            'reference_count' => $top['count'],
            'reference_transaction_id' => $referenceTransaction->id,
            'matched_classification_rule_id' => null,
        ];
    }

    private function matchesRule(ClassificationRule $classificationRule, Transaction $transaction): bool
    {
        $transactionType = $classificationRule->transaction_type;

        if (
            $transactionType !== null
            && $transactionType !== 'any'
            && $transactionType !== $transaction->type
        ) {
            return false;
        }

        $fieldValue = match ($classificationRule->match_field) {
            'merchant_name' => $transaction->merchant_name,
            'description' => $transaction->description,
            'account_name' => $transaction->account?->name,
            default => null,
        };

        $normalizedFieldValue = $this->normalize((string) ($fieldValue ?? ''));
        $normalizedMatchValue = $this->normalize($classificationRule->match_value);

        if ($normalizedFieldValue === '' || $normalizedMatchValue === '') {
            return false;
        }

        return match ($classificationRule->match_operator) {
            'equals' => $normalizedFieldValue === $normalizedMatchValue,
            'starts_with' => str_starts_with($normalizedFieldValue, $normalizedMatchValue),
            'contains' => str_contains($normalizedFieldValue, $normalizedMatchValue),
            default => false,
        };
    }

    private function categoryMatchesType(?Category $category, string $transactionType): bool
    {
        return $category !== null
            && $category->name !== '未分類'
            && in_array($category->type, [$transactionType, 'both'], true);
    }

    private function subcategoryMatchesCategory(?Subcategory $subcategory, ?Category $category): bool
    {
        return $subcategory === null
            || ($category !== null
                && $subcategory->name !== '未分類'
                && $subcategory->category_id === $category->id);
    }

    /**
     * @return array<string, mixed>
     */
    private function transactionDetails(Transaction $transaction): array
    {
        return [
            'transaction_id' => $transaction->id,
            'user_id' => $transaction->user_id,
            'type' => $transaction->type,
            'transaction_date' => $transaction->transaction_date?->format('Y-m-d'),
            'amount' => number_format((float) $transaction->amount, 2, '.', ''),
            'currency' => $transaction->currency,
            'account_name' => $transaction->account?->name,
            'merchant_name' => $transaction->merchant_name,
            'description' => $transaction->description,
            'payment_method_label' => $transaction->payment_method_label,
            'memo' => $transaction->memo,
            'is_confirmed' => $transaction->is_confirmed,
            'current_category' => $transaction->category?->name,
            'current_subcategory' => $transaction->subcategory?->name,
        ];
    }

    private function normalize(string $value): string
    {
        return Str::lower(Str::squish($value));
    }
}
