<?php

namespace App\Services\Diagnostics;

use App\Models\Transaction;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class DiagnoseTransactionsService
{
    /**
     * @return array<int, string>
     */
    public static function reasons(): array
    {
        return [
            'カード引落が expense として記録されている候補',
            'コード決済請求付替が expense として記録されている候補',
            '現金引き出し/チャージが expense として記録されている候補',
            '積立フローの二重表現候補',
            'カテゴリ付き transfer の確認候補',
            'shopping 系 transfer の確認候補',
            '未分類カテゴリ実体の確認候補',
        ];
    }

    /**
     * @return Collection<int, array{
     *     transaction_id:int,
     *     user_id:int,
     *     transaction_date:string,
     *     type:string,
     *     account_name:?string,
     *     transfer_account_name:?string,
     *     amount:string,
     *     currency:string,
     *     merchant_name:?string,
     *     description:?string,
     *     memo:?string,
     *     suspicion_reason:string
     * }>
     */
    public function handle(?int $userId = null, ?string $reason = null, int $limit = 50): Collection
    {
        $reasonSet = $reason === null ? self::reasons() : [$reason];

        $candidates = collect();

        foreach ($reasonSet as $currentReason) {
            $candidates = $candidates->concat(match ($currentReason) {
                'カード引落が expense として記録されている候補' => $this->cardWithdrawalRecordedAsExpense($userId),
                'コード決済請求付替が expense として記録されている候補' => $this->codePaymentTransferRecordedAsExpense($userId),
                '現金引き出し/チャージが expense として記録されている候補' => $this->cashWithdrawalOrChargeRecordedAsExpense($userId),
                '積立フローの二重表現候補' => $this->duplicatedInvestmentFlowCandidate($userId),
                'カテゴリ付き transfer の確認候補' => $this->categorizedTransfer($userId),
                'shopping 系 transfer の確認候補' => $this->shoppingLikeTransfer($userId),
                '未分類カテゴリ実体の確認候補' => $this->uncategorizedCategoryEntity($userId),
                default => collect(),
            });
        }

        return $candidates
            ->sortBy([
                ['transaction_date', 'desc'],
                ['transaction_id', 'desc'],
            ])
            ->values()
            ->take($limit);
    }

    /**
     * @return Collection<int, array{
     *     suspicion_reason:string,
     *     count:int,
     *     latest_date:?string,
     *     oldest_date:?string
     * }>
     */
    public function summary(?int $userId = null): Collection
    {
        return collect(self::reasons())
            ->map(function (string $reason) use ($userId): array {
                $candidates = $this->handle($userId, $reason, PHP_INT_MAX);

                return [
                    'suspicion_reason' => $reason,
                    'count' => $candidates->count(),
                    'latest_date' => $candidates->max('transaction_date'),
                    'oldest_date' => $candidates->min('transaction_date'),
                ];
            })
            ->values();
    }

    /**
     * @return Collection<int, array{
     *     transaction_id:int,
     *     user_id:int,
     *     transaction_date:string,
     *     type:string,
     *     account_name:?string,
     *     transfer_account_name:?string,
     *     amount:string,
     *     currency:string,
     *     merchant_name:?string,
     *     description:?string,
     *     memo:?string,
     *     suspicion_reason:string
     * }>
     */
    public function handleTransaction(Transaction $transaction): Collection
    {
        $transaction->loadMissing(['account', 'transferAccount', 'category', 'subcategory']);

        $candidates = collect();

        if ($this->isCardWithdrawalRecordedAsExpense($transaction)) {
            $candidates->push($this->formatCandidate(
                $transaction,
                'カード引落が expense として記録されている候補',
            ));
        }

        if ($this->isCodePaymentTransferRecordedAsExpense($transaction)) {
            $candidates->push($this->formatCandidate(
                $transaction,
                'コード決済請求付替が expense として記録されている候補',
            ));
        }

        if ($this->isCashWithdrawalOrChargeRecordedAsExpense($transaction)) {
            $candidates->push($this->formatCandidate(
                $transaction,
                '現金引き出し/チャージが expense として記録されている候補',
            ));
        }

        if ($this->isDuplicatedInvestmentFlowCandidate($transaction)) {
            $candidates->push($this->formatCandidate(
                $transaction,
                '積立フローの二重表現候補',
            ));
        }

        if ($this->isCategorizedTransfer($transaction)) {
            $candidates->push($this->formatCandidate(
                $transaction,
                'カテゴリ付き transfer の確認候補',
            ));
        }

        if ($this->isShoppingLikeTransfer($transaction)) {
            $candidates->push($this->formatCandidate(
                $transaction,
                'shopping 系 transfer の確認候補',
            ));
        }

        if ($this->isUncategorizedCategoryEntity($transaction)) {
            $candidates->push($this->formatCandidate(
                $transaction,
                '未分類カテゴリ実体の確認候補',
            ));
        }

        return $candidates->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function cardWithdrawalRecordedAsExpense(?int $userId): Collection
    {
        return $this->baseQuery($userId)
            ->where('type', 'expense')
            ->whereHas('account', fn ($query) => $query->where('type', 'bank'))
            ->get()
            ->filter(fn (Transaction $transaction): bool => $this->containsAnyKeyword($transaction, $this->cardWithdrawalKeywords()))
            ->map(fn (Transaction $transaction): array => $this->formatCandidate(
                $transaction,
                'カード引落が expense として記録されている候補',
            ))
            ->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function codePaymentTransferRecordedAsExpense(?int $userId): Collection
    {
        return $this->baseQuery($userId)
            ->where('type', 'expense')
            ->whereHas('account', fn ($query) => $query->whereIn('type', ['bank', 'credit_card', 'other']))
            ->get()
            ->filter(fn (Transaction $transaction): bool => $this->isCodePaymentTransferRecordedAsExpense($transaction))
            ->map(fn (Transaction $transaction): array => $this->formatCandidate(
                $transaction,
                'コード決済請求付替が expense として記録されている候補',
            ))
            ->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function cashWithdrawalOrChargeRecordedAsExpense(?int $userId): Collection
    {
        return $this->baseQuery($userId)
            ->where('type', 'expense')
            ->whereHas('account', fn ($query) => $query->whereIn('type', ['bank', 'cash', 'credit_card', 'e_money', 'other']))
            ->get()
            ->filter(fn (Transaction $transaction): bool => $this->isCashWithdrawalOrChargeRecordedAsExpense($transaction))
            ->map(fn (Transaction $transaction): array => $this->formatCandidate(
                $transaction,
                '現金引き出し/チャージが expense として記録されている候補',
            ))
            ->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function duplicatedInvestmentFlowCandidate(?int $userId): Collection
    {
        $transferCandidates = $this->baseQuery($userId)
            ->where('type', 'transfer')
            ->whereHas('transferAccount', fn ($query) => $query->where('type', 'securities'))
            ->get();

        $transferGroups = $transferCandidates
            ->groupBy(fn (Transaction $transaction): string => implode('|', [
                $transaction->user_id,
                $transaction->transaction_date?->format('Y-m-d') ?? '',
                $transaction->amount,
                $transaction->transfer_account_id ?? 0,
            ]))
            ->filter(fn (Collection $group): bool => $group->pluck('account_id')->filter()->unique()->count() > 1);

        return $transferGroups
            ->flatMap(fn (Collection $group): Collection => $group->map(
                fn (Transaction $transaction): array => $this->formatCandidate(
                    $transaction,
                    '積立フローの二重表現候補',
                ),
            ))
            ->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function categorizedTransfer(?int $userId): Collection
    {
        return $this->baseQuery($userId)
            ->where('type', 'transfer')
            ->get()
            ->filter(fn (Transaction $transaction): bool => $this->isCategorizedTransfer($transaction))
            ->map(fn (Transaction $transaction): array => $this->formatCandidate(
                $transaction,
                'カテゴリ付き transfer の確認候補',
            ))
            ->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function shoppingLikeTransfer(?int $userId): Collection
    {
        return $this->baseQuery($userId)
            ->where('type', 'transfer')
            ->get()
            ->filter(fn (Transaction $transaction): bool => $this->isShoppingLikeTransfer($transaction))
            ->map(fn (Transaction $transaction): array => $this->formatCandidate(
                $transaction,
                'shopping 系 transfer の確認候補',
            ))
            ->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function uncategorizedCategoryEntity(?int $userId): Collection
    {
        return $this->baseQuery($userId)
            ->where(function ($query): void {
                $query
                    ->whereHas('category', fn ($categoryQuery) => $this->whereUncategorizedNameCandidate($categoryQuery))
                    ->orWhereHas('subcategory', fn ($subcategoryQuery) => $this->whereUncategorizedNameCandidate($subcategoryQuery));
            })
            ->get()
            ->filter(fn (Transaction $transaction): bool => $this->isUncategorizedCategoryEntity($transaction))
            ->map(fn (Transaction $transaction): array => $this->formatCandidate(
                $transaction,
                '未分類カテゴリ実体の確認候補',
            ))
            ->values();
    }

    private function baseQuery(?int $userId)
    {
        return Transaction::query()
            ->with(['account', 'transferAccount', 'category', 'subcategory'])
            ->when($userId !== null, fn ($query) => $query->where('user_id', $userId))
            ->orderByDesc('transaction_date')
            ->orderByDesc('id');
    }

    private function isCardWithdrawalRecordedAsExpense(Transaction $transaction): bool
    {
        return $transaction->type === 'expense'
            && $transaction->account?->type === 'bank'
            && $this->containsAnyKeyword($transaction, $this->cardWithdrawalKeywords());
    }

    private function isCodePaymentTransferRecordedAsExpense(Transaction $transaction): bool
    {
        return $transaction->type === 'expense'
            && in_array($transaction->account?->type, ['bank', 'credit_card', 'other'], true)
            && ! $this->isCashWithdrawalOrChargeRecordedAsExpense($transaction)
            && $this->containsCodePaymentTransferSignal($transaction);
    }

    private function containsCodePaymentTransferSignal(Transaction $transaction): bool
    {
        return $this->containsAnyNormalizedKeywordPairInSingleValue(
            [
                $transaction->merchant_name,
                $transaction->description,
                $transaction->memo,
                $transaction->payment_method_label,
            ],
            $this->codePaymentKeywords(),
            $this->codePaymentTransferSignalKeywords(),
        );
    }

    /**
     * 指定された取引が現金引き出しまたはチャージとして費用として記録されているかを判定します。
     *
     * @param  Transaction  $transaction  判定対象の取引オブジェクト
     * @return bool 現金引き出しまたはチャージが費用として記録されている場合はtrue、それ以外はfalse
     */
    private function isCashWithdrawalOrChargeRecordedAsExpense(Transaction $transaction): bool
    {
        if ($transaction->type !== 'expense') {
            return false;
        }

        if (
            $transaction->account?->type === 'bank'
            && $this->containsAnyNormalizedKeyword($transaction, $this->cashWithdrawalKeywords())
        ) {
            return true;
        }

        if (! in_array($transaction->account?->type, ['bank', 'cash', 'credit_card', 'e_money', 'other'], true)) {
            return false;
        }

        $walletValues = [
            $transaction->merchant_name,
            $transaction->description,
            $transaction->memo,
            $transaction->payment_method_label,
        ];

        if ($this->containsAnyNormalizedKeywordInValues($walletValues, $this->walletChargePhraseKeywords())) {
            return true;
        }

        if (
            $this->containsAnyNormalizedKeywordPairInSingleValue(
                $walletValues,
                $this->walletNames(),
                $this->walletChargeSignalKeywords(),
            )
        ) {
            return true;
        }

        return $transaction->account?->type === 'bank'
            && $this->containsAnyNormalizedKeywordInValues($walletValues, ['PAYPAY'])
            && $this->containsAnyNormalizedKeywordInValues($walletValues, ['バンクPOS']);
    }

    /**
     * 指定されたトランザクションが重複した投資フローの候補であるかどうかを判定します。
     *
     * @param  Transaction  $transaction  判断対象となるトランザクションオブジェクト
     * @return bool 重複した投資フローの候補である場合は true、それ以外の場合は false
     */
    private function isDuplicatedInvestmentFlowCandidate(Transaction $transaction): bool
    {
        if (
            $transaction->type !== 'transfer'
            || $transaction->transfer_account_id === null
            || $transaction->transferAccount?->type !== 'securities'
            || $transaction->transaction_date === null
        ) {
            return false;
        }

        $sameInvestmentTransfers = Transaction::query()
            ->where('user_id', $transaction->user_id)
            ->where('type', 'transfer')
            ->whereDate('transaction_date', $transaction->transaction_date->format('Y-m-d'))
            ->where('amount', $transaction->amount)
            ->where('transfer_account_id', $transaction->transfer_account_id)
            ->whereHas('transferAccount', fn ($query) => $query->where('type', 'securities'))
            ->get(['id', 'account_id']);

        return $sameInvestmentTransfers
            ->pluck('account_id')
            ->filter()
            ->unique()
            ->count() > 1;
    }

    private function isCategorizedTransfer(Transaction $transaction): bool
    {
        return $transaction->type === 'transfer'
            && ($transaction->category_id !== null || $transaction->subcategory_id !== null);
    }

    private function isShoppingLikeTransfer(Transaction $transaction): bool
    {
        return $transaction->type === 'transfer'
            && $this->containsAnyKeyword($transaction, $this->shoppingKeywords())
            && ! $this->hasExpectedShoppingExpensePair($transaction);
    }

    private function hasExpectedShoppingExpensePair(Transaction $transaction): bool
    {
        if (
            $transaction->transaction_date === null
            || $transaction->account?->type !== 'credit_card'
            || $transaction->transferAccount?->type !== 'e_money'
            || $transaction->transfer_account_id === null
            || $transaction->category_id !== null
            || $transaction->subcategory_id !== null
            || $transaction->is_calculation_target
        ) {
            return false;
        }

        return Transaction::query()
            ->where('user_id', $transaction->user_id)
            ->whereKeyNot($transaction->id)
            ->where('type', 'expense')
            ->where('account_id', $transaction->transfer_account_id)
            ->whereDate('transaction_date', $transaction->transaction_date->format('Y-m-d'))
            ->where('amount', $transaction->amount)
            ->where('currency', $transaction->currency)
            ->exists();
    }

    private function isUncategorizedCategoryEntity(Transaction $transaction): bool
    {
        return $this->isUncategorizedName($transaction->category?->name)
            || $this->isUncategorizedName($transaction->subcategory?->name);
    }

    /**
     * @param  array<int, string>  $keywords
     */
    private function containsAnyKeyword(Transaction $transaction, array $keywords): bool
    {
        $haystack = Str::lower(implode(' ', array_filter([
            $transaction->merchant_name,
            $transaction->description,
            $transaction->memo,
            $transaction->payment_method_label,
        ], fn ($value): bool => is_string($value) && trim($value) !== '')));

        if ($haystack === '') {
            return false;
        }

        foreach ($keywords as $keyword) {
            if (Str::contains($haystack, Str::lower($keyword))) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int, string>  $keywords
     */
    private function containsAnyNormalizedKeyword(Transaction $transaction, array $keywords): bool
    {
        return $this->containsAnyNormalizedKeywordInValues([
            $transaction->merchant_name,
            $transaction->description,
            $transaction->memo,
            $transaction->payment_method_label,
        ], $keywords);
    }

    /**
     * @param  array<int, mixed>  $values
     * @param  array<int, string>  $keywords
     */
    private function containsAnyNormalizedKeywordInValues(array $values, array $keywords): bool
    {
        $haystack = $this->normalizeTextForKeywordSearch(implode(' ', array_filter(
            $values,
            fn ($value): bool => is_string($value) && trim($value) !== '',
        )));

        if ($haystack === '') {
            return false;
        }

        foreach ($keywords as $keyword) {
            if (Str::contains($haystack, $this->normalizeTextForKeywordSearch($keyword))) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int, mixed>  $values
     * @param  array<int, string>  $firstKeywords
     * @param  array<int, string>  $secondKeywords
     */
    private function containsAnyNormalizedKeywordPairInSingleValue(
        array $values,
        array $firstKeywords,
        array $secondKeywords,
    ): bool {
        foreach ($values as $value) {
            if (! is_string($value) || trim($value) === '') {
                continue;
            }

            $normalizedValue = $this->normalizeTextForKeywordSearch($value);
            $hasFirstKeyword = false;

            foreach ($firstKeywords as $keyword) {
                if (Str::contains($normalizedValue, $this->normalizeTextForKeywordSearch($keyword))) {
                    $hasFirstKeyword = true;
                    break;
                }
            }

            if (! $hasFirstKeyword) {
                continue;
            }

            foreach ($secondKeywords as $keyword) {
                if (Str::contains($normalizedValue, $this->normalizeTextForKeywordSearch($keyword))) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @return array<int, string>
     */
    private function cardWithdrawalKeywords(): array
    {
        return [
            '引落',
            'クレジット',
            'visa',
            'mastercard',
            'master',
            'jcb',
            'amex',
            'diners',
            '三井住友カード',
            'dカード',
            'dcard',
        ];
    }

    /**
     * @return array<int, string>
     */
    private function codePaymentKeywords(): array
    {
        return [
            'd払い',
            'paypay',
            'kyash',
            '楽天キャッシュ',
            'rakuten cash',
        ];
    }

    /**
     * @return array<int, string>
     */
    private function codePaymentTransferSignalKeywords(): array
    {
        return [
            '請求',
            '付替',
            '付け替',
            'B/',
        ];
    }

    /**
     * @return array<int, string>
     */
    private function cashWithdrawalKeywords(): array
    {
        return [
            '現金引出',
            '現金引き出し',
            'ATM引出',
            'ATM引き出し',
        ];
    }

    /**
     * @return array<int, string>
     */
    private function walletChargePhraseKeywords(): array
    {
        return [
            'モバイルSuicaチャージ',
            'モバイルSuicaチャ-ジ',
            'モバイルＳｕｉｃａチャージ',
            'モバイルＳｕｉｃａチャ-ジ',
            'PayPayチャージ',
            'PayPay入金',
            'Kyashチャージ',
            'Kyash入金',
        ];
    }

    /**
     * @return array<int, string>
     */
    private function walletNames(): array
    {
        return [
            'PayPay',
            'Kyash',
            'モバイルSuica',
            'モバイルＳｕｉｃａ',
        ];
    }

    /**
     * @return array<int, string>
     */
    private function walletChargeSignalKeywords(): array
    {
        return [
            'チャージ',
            'チャ-ジ',
            '入金',
        ];
    }

    /**
     * @return array<int, string>
     */
    private function shoppingKeywords(): array
    {
        return [
            'スーパー',
            'コンビニ',
            'ドラッグ',
            'レストラン',
            'カフェ',
            'やよい軒',
            'マルエツ',
        ];
    }

    private function isUncategorizedName(?string $value): bool
    {
        if ($value === null) {
            return false;
        }

        $normalized = mb_convert_kana($value, 'asKV', 'UTF-8');
        $normalized = preg_replace('/\s+/u', '', $normalized) ?? $normalized;

        return Str::lower($normalized) === '未分類';
    }

    private function normalizeTextForKeywordSearch(string $value): string
    {
        $normalized = mb_convert_kana($value, 'asKV', 'UTF-8');
        $normalized = preg_replace('/\s+/u', '', $normalized) ?? $normalized;

        return Str::lower($normalized);
    }

    /**
     * 指定されたクエリに対して、未分類の候補名を検索する条件を追加します。
     *
     * @param  mixed  $query  クエリビルダーオブジェクト
     */
    private function whereUncategorizedNameCandidate($query): void
    {
        $query->where(function ($nameQuery): void {
            $nameQuery
                ->where('name', 'like', '%未分類%')
                ->orWhere('name', 'like', '%未%分%類%')
                ->orWhere('name', 'like', '%ﾐ%分%類%');
        });
    }

    /**
     * @return array{
     *     transaction_id:int,
     *     user_id:int,
     *     transaction_date:string,
     *     type:string,
     *     account_name:?string,
     *     transfer_account_name:?string,
     *     amount:string,
     *     currency:string,
     *     merchant_name:?string,
     *     description:?string,
     *     memo:?string,
     *     suspicion_reason:string
     * }
     */
    private function formatCandidate(Transaction $transaction, string $reason): array
    {
        return [
            'transaction_id' => $transaction->id,
            'user_id' => $transaction->user_id,
            'transaction_date' => $transaction->transaction_date?->format('Y-m-d') ?? '',
            'type' => $transaction->type,
            'account_name' => $transaction->account?->name,
            'transfer_account_name' => $transaction->transferAccount?->name,
            'amount' => (string) $transaction->amount,
            'currency' => $transaction->currency,
            'merchant_name' => $transaction->merchant_name,
            'description' => $transaction->description,
            'memo' => $transaction->memo,
            'suspicion_reason' => $reason,
        ];
    }
}
