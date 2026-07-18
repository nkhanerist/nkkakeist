<?php

namespace App\Services\Diagnostics;

use App\Models\Account;
use App\Models\Transaction;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class SuggestTransactionFixesService
{
    public function __construct(
        private readonly DiagnoseTransactionsService $diagnoseTransactionsService,
    ) {}

    /**
     * @return Collection<int, array{
     *     transaction_id:int,
     *     user_id:int,
     *     current_type:string,
     *     suggested_action:string,
     *     suggested_type:?string,
     *     suggested_source_account:?string,
     *     suggested_transfer_account:?string,
     *     reason:string,
     *     note:string,
     *     transaction_date:?string,
     *     amount:string,
     *     currency:string,
     *     merchant_name:?string,
     *     description:?string,
     *     memo:?string,
     *     current_category:?string,
     *     current_subcategory:?string
     * }>
     */
    public function handle(?int $userId = null, ?string $reason = null, int $limit = 50, ?string $action = null): Collection
    {
        $diagnoseLimit = $action === null ? $limit : PHP_INT_MAX;
        $candidates = $reason === null
            ? collect(DiagnoseTransactionsService::reasons())
                ->flatMap(fn (string $diagnoseReason): Collection => $this->diagnoseTransactionsService->handle($userId, $diagnoseReason, $diagnoseLimit))
            : $this->diagnoseTransactionsService->handle($userId, $reason, $diagnoseLimit);

        $suggestions = $candidates
            ->map(fn (array $candidate): ?array => $this->buildSuggestion($candidate))
            ->filter()
            ->sortBy(fn (array $suggestion): int => $this->reasonPriority((string) $suggestion['reason']));

        if ($action !== null) {
            $suggestions = $suggestions
                ->filter(fn (array $suggestion): bool => (string) $suggestion['suggested_action'] === $action);
        }

        return $suggestions
            ->unique('transaction_id')
            ->values()
            ->take($limit);
    }

    /**
     * @return Collection<int, array{
     *     transaction_id:int,
     *     user_id:int,
     *     current_type:string,
     *     suggested_action:string,
     *     suggested_type:?string,
     *     suggested_source_account:?string,
     *     suggested_transfer_account:?string,
     *     reason:string,
     *     note:string,
     *     transaction_date:?string,
     *     amount:string,
     *     currency:string,
     *     merchant_name:?string,
     *     description:?string,
     *     memo:?string,
     *     current_category:?string,
     *     current_subcategory:?string
     * }>
     */
    public function explain(Transaction $transaction): Collection
    {
        return $this->diagnoseTransactionsService
            ->handleTransaction($transaction)
            ->map(fn (array $candidate): ?array => $this->buildSuggestion($candidate))
            ->filter()
            ->sortBy(fn (array $suggestion): int => $this->reasonPriority((string) $suggestion['reason']))
            ->values();
    }

    /**
     * @param  array<string, mixed>  $candidate
     * @return array{
     *     transaction_id:int,
     *     user_id:int,
     *     current_type:string,
     *     suggested_action:string,
     *     suggested_type:?string,
     *     suggested_source_account:?string,
     *     suggested_transfer_account:?string,
     *     reason:string,
     *     note:string,
     *     transaction_date:?string,
     *     amount:string,
     *     currency:string,
     *     merchant_name:?string,
     *     description:?string,
     *     memo:?string,
     *     current_category:?string,
     *     current_subcategory:?string
     * }|null
     */
    private function buildSuggestion(array $candidate): ?array
    {
        $transactionId = data_get($candidate, 'transaction_id');

        if (! is_int($transactionId)) {
            return null;
        }

        $transaction = Transaction::query()
            ->with(['account', 'transferAccount', 'category', 'subcategory', 'user.accounts'])
            ->find($transactionId);

        if (! $transaction instanceof Transaction) {
            return null;
        }

        $reason = (string) data_get($candidate, 'suspicion_reason', '');

        $suggestion = match ($reason) {
            'カード引落が expense として記録されている候補' => $this->cardWithdrawalSuggestion($transaction, $reason),
            'コード決済請求付替が expense として記録されている候補' => $this->codePaymentSuggestion($transaction, $reason),
            '現金引き出し/チャージが expense として記録されている候補' => $this->cashWithdrawalOrChargeSuggestion($transaction, $reason),
            '積立フローの二重表現候補' => $this->duplicatedInvestmentSuggestion($transaction, $reason),
            'カテゴリ付き transfer の確認候補' => $this->categorizedTransferSuggestion($transaction, $reason),
            'shopping 系 transfer の確認候補' => $this->shoppingLikeTransferSuggestion($transaction, $reason),
            '未分類カテゴリ実体の確認候補' => $this->uncategorizedCategoryEntitySuggestion($transaction, $reason),
            default => null,
        };

        if ($suggestion === null) {
            return null;
        }

        return [
            ...$suggestion,
            ...$this->transactionDetails($transaction),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function transactionDetails(Transaction $transaction): array
    {
        return [
            'transaction_date' => $transaction->transaction_date?->format('Y-m-d'),
            'amount' => $this->normalizedAmount($transaction),
            'currency' => $transaction->currency,
            'merchant_name' => $transaction->merchant_name,
            'description' => $transaction->description,
            'memo' => $transaction->memo,
            'current_category' => $transaction->category?->name,
            'current_subcategory' => $transaction->subcategory?->name,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function cardWithdrawalSuggestion(Transaction $transaction, string $reason): array
    {
        $suggestedTransferAccount = $this->inferCounterpartyAccountName(
            $transaction,
            fn (Account $account): bool => $account->type === 'credit_card',
        );

        return [
            'transaction_id' => $transaction->id,
            'user_id' => $transaction->user_id,
            'current_type' => $transaction->type,
            'suggested_action' => 'transfer へ見直し確認',
            'suggested_type' => 'transfer',
            'suggested_source_account' => $transaction->account?->name,
            'suggested_transfer_account' => $suggestedTransferAccount,
            'reason' => $reason,
            'note' => $suggestedTransferAccount !== null
                ? 'クレジットカード引落なら expense ではなく bank -> credit_card transfer が自然です。'
                : 'クレジットカード引落なら expense ではなく bank -> credit_card transfer が自然です。相手カード口座の確認が必要です。',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function codePaymentSuggestion(Transaction $transaction, string $reason): array
    {
        $suggestedTransferAccount = $this->inferCounterpartyAccountName(
            $transaction,
            fn (Account $account): bool => in_array($account->type, ['e_money', 'other'], true),
        );

        return [
            'transaction_id' => $transaction->id,
            'user_id' => $transaction->user_id,
            'current_type' => $transaction->type,
            'suggested_action' => 'transfer へ見直し確認',
            'suggested_type' => 'transfer',
            'suggested_source_account' => $transaction->account?->name,
            'suggested_transfer_account' => $suggestedTransferAccount,
            'reason' => $reason,
            'note' => $suggestedTransferAccount !== null
                ? '請求付替なら expense ではなく transfer が自然です。実際に消費した取引が別にあるか確認してください。'
                : '請求付替なら expense ではなく transfer が自然です。実際に消費した取引が別にあるかと相手口座を確認してください。',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function cashWithdrawalOrChargeSuggestion(Transaction $transaction, string $reason): array
    {
        $suggestedTransferAccount = $this->inferCounterpartyAccount(
            $transaction,
            fn (Account $account): bool => in_array($account->type, ['cash', 'e_money'], true),
        );
        $relatedTransfer = $this->findSameDaySameAmountTransfer($transaction, $suggestedTransferAccount);

        return [
            'transaction_id' => $transaction->id,
            'user_id' => $transaction->user_id,
            'current_type' => $transaction->type,
            'suggested_action' => 'transfer へ見直し確認',
            'suggested_type' => 'transfer',
            'suggested_source_account' => $transaction->account?->name,
            'suggested_transfer_account' => $suggestedTransferAccount?->name,
            'reason' => $reason,
            'note' => $this->cashWithdrawalOrChargeNote($suggestedTransferAccount?->name, $relatedTransfer),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function duplicatedInvestmentSuggestion(Transaction $transaction, string $reason): array
    {
        return [
            'transaction_id' => $transaction->id,
            'user_id' => $transaction->user_id,
            'current_type' => $transaction->type,
            'suggested_action' => '二重表現確認',
            'suggested_type' => $transaction->type,
            'suggested_source_account' => $transaction->account?->name,
            'suggested_transfer_account' => $transaction->transferAccount?->name,
            'reason' => $reason,
            'note' => '同日同額で複数の積立 transfer があり、同じ積立を重複表現している可能性があります。d払い経由 / dcard 直 / 銀行直 のどれが実態か確認してください。',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function categorizedTransferSuggestion(Transaction $transaction, string $reason): array
    {
        return [
            'transaction_id' => $transaction->id,
            'user_id' => $transaction->user_id,
            'current_type' => $transaction->type,
            'suggested_action' => 'カテゴリ削除確認',
            'suggested_type' => 'transfer',
            'suggested_source_account' => $transaction->account?->name,
            'suggested_transfer_account' => $transaction->transferAccount?->name,
            'reason' => $reason,
            'note' => 'このアプリでは transfer はカテゴリを持たない前提です。Money Forward 由来の補助カテゴリなら削除候補です。',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function shoppingLikeTransferSuggestion(Transaction $transaction, string $reason): array
    {
        $relatedTransactions = $this->sameDaySameAmountTransactions($transaction);
        $relatedExpenses = $relatedTransactions
            ->filter(fn (Transaction $relatedTransaction): bool => $relatedTransaction->type === 'expense')
            ->values();
        $hasRelatedExpenses = $relatedExpenses->isNotEmpty();

        return [
            'transaction_id' => $transaction->id,
            'user_id' => $transaction->user_id,
            'current_type' => $transaction->type,
            'suggested_action' => $hasRelatedExpenses ? '二重表現確認' : 'expense 化確認',
            'suggested_type' => $hasRelatedExpenses ? 'transfer' : 'expense',
            'suggested_source_account' => $transaction->account?->name,
            'suggested_transfer_account' => $transaction->transferAccount?->name,
            'reason' => $reason,
            'note' => $this->shoppingLikeTransferNote($relatedTransactions, $relatedExpenses),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function uncategorizedCategoryEntitySuggestion(Transaction $transaction, string $reason): array
    {
        $categoryIsUncategorized = $this->isUncategorizedName($transaction->category?->name);

        return [
            'transaction_id' => $transaction->id,
            'user_id' => $transaction->user_id,
            'current_type' => $transaction->type,
            'suggested_action' => $categoryIsUncategorized ? 'カテゴリ未設定へ見直し確認' : 'サブカテゴリ未設定へ見直し確認',
            'suggested_type' => $transaction->type,
            'suggested_source_account' => $transaction->account?->name,
            'suggested_transfer_account' => $transaction->transferAccount?->name,
            'reason' => $reason,
            'note' => $this->uncategorizedCategoryEntityNote($transaction, $categoryIsUncategorized),
        ];
    }

    private function uncategorizedCategoryEntityNote(Transaction $transaction, bool $categoryIsUncategorized): string
    {
        $baseNote = $categoryIsUncategorized
            ? 'Money Forward の未分類はカテゴリ実体ではなく category_id=null / subcategory_id=null 扱いが自然です。実カテゴリへ分類すべきかも確認してください。'
            : 'Money Forward の未分類サブカテゴリは実体ではなく subcategory_id=null 扱いが自然です。category は維持し、必要なら実サブカテゴリへ分類してください。';

        $additionalNotes = [];

        if ($this->containsPointUsageMarker($transaction)) {
            $additionalNotes[] = '摘要にポイント利用分が含まれます。ポイント相殺の income/expense ペアなら、両方とも未分類カテゴリ実体を null にするか確認してください。';
        }

        $relatedTransactions = $this->sameDaySameAmountTransactions($transaction);

        if ($relatedTransactions->isNotEmpty()) {
            $additionalNotes[] = sprintf(
                '同日同額の関連 transaction が %d 件あります（代表ID: %s、代表明細: %s）。',
                $relatedTransactions->count(),
                $relatedTransactions->pluck('id')->take(3)->implode(', '),
                $this->relatedTransactionSummary($relatedTransactions->first()),
            );
        }

        if ($additionalNotes === []) {
            return $baseNote;
        }

        return $baseNote.' '.implode(' ', $additionalNotes);
    }

    /**
     * @param  callable(Account): bool  $accountFilter
     */
    private function inferCounterpartyAccountName(Transaction $transaction, callable $accountFilter): ?string
    {
        return $this->inferCounterpartyAccount($transaction, $accountFilter)?->name;
    }

    /**
     * @param  callable(Account): bool  $accountFilter
     */
    private function inferCounterpartyAccount(Transaction $transaction, callable $accountFilter): ?Account
    {
        $haystack = $this->normalizeText(implode(' ', array_filter([
            $transaction->merchant_name,
            $transaction->description,
            $transaction->memo,
            $transaction->payment_method_label,
        ], fn ($value): bool => is_string($value) && trim($value) !== '')));

        if ($haystack === '') {
            return null;
        }

        $matchedAccounts = $transaction->user->accounts
            ->reject(fn (Account $account): bool => $account->id === $transaction->account_id)
            ->filter($accountFilter)
            ->filter(function (Account $account) use ($transaction): bool {
                $expectedCurrency = $transaction->account?->currency ?? $transaction->currency;

                return $expectedCurrency === null || $expectedCurrency === '' || $account->currency === $expectedCurrency;
            })
            ->filter(function (Account $account) use ($haystack): bool {
                $needles = collect([$account->name, ...($account->import_aliases ?? [])])
                    ->filter(fn ($value): bool => is_string($value) && trim($value) !== '')
                    ->map(fn (string $value): string => $this->normalizeText($value))
                    ->unique();

                foreach ($needles as $needle) {
                    if ($needle !== '' && Str::contains($haystack, $needle)) {
                        return true;
                    }
                }

                return false;
            })
            ->values();

        if ($matchedAccounts->count() !== 1) {
            return null;
        }

        return $matchedAccounts->first();
    }

    private function findSameDaySameAmountTransfer(Transaction $transaction, ?Account $suggestedTransferAccount): ?Transaction
    {
        if ($transaction->transaction_date === null) {
            return null;
        }

        $query = Transaction::query()
            ->with(['account', 'transferAccount'])
            ->where('user_id', $transaction->user_id)
            ->whereKeyNot($transaction->id)
            ->where('type', 'transfer')
            ->whereDate('transaction_date', $transaction->transaction_date->format('Y-m-d'))
            ->where('amount', $transaction->amount)
            ->where('currency', $transaction->currency)
            ->where(function ($query) use ($transaction): void {
                $query->where('account_id', $transaction->account_id)
                    ->orWhere('transfer_account_id', $transaction->account_id);
            });

        if ($suggestedTransferAccount instanceof Account) {
            $query->where(function ($query) use ($suggestedTransferAccount): void {
                $query->where('account_id', $suggestedTransferAccount->id)
                    ->orWhere('transfer_account_id', $suggestedTransferAccount->id);
            });
        }

        return $query->orderByDesc('id')->first();
    }

    /**
     * @return Collection<int, Transaction>
     */
    private function sameDaySameAmountTransactions(Transaction $transaction): Collection
    {
        if ($transaction->transaction_date === null) {
            return collect();
        }

        return Transaction::query()
            ->with(['account', 'transferAccount', 'category', 'subcategory'])
            ->where('user_id', $transaction->user_id)
            ->whereKeyNot($transaction->id)
            ->whereDate('transaction_date', $transaction->transaction_date->format('Y-m-d'))
            ->where('amount', $transaction->amount)
            ->where('currency', $transaction->currency)
            ->orderBy('id')
            ->get()
            ->filter(fn (Transaction $relatedTransaction): bool => $this->normalizedAmount($relatedTransaction) === $this->normalizedAmount($transaction))
            ->values();
    }

    /**
     * @param  Collection<int, Transaction>  $relatedTransactions
     * @param  Collection<int, Transaction>  $relatedExpenses
     */
    private function shoppingLikeTransferNote(Collection $relatedTransactions, Collection $relatedExpenses): string
    {
        if ($relatedExpenses->isNotEmpty()) {
            return sprintf(
                'shopping 系キーワードを含む transfer です。同日同額の expense が %d 件あります（代表ID: %s、代表明細: %s）。実支出が別に記録済みなら、この transfer は二重表現回避のため口座間移動か重複取込か確認してください。',
                $relatedExpenses->count(),
                $relatedExpenses->pluck('id')->take(3)->implode(', '),
                $this->relatedTransactionSummary($relatedExpenses->first()),
            );
        }

        if ($relatedTransactions->isNotEmpty()) {
            return sprintf(
                'shopping 系キーワードを含む transfer です。同日同額の関連 transaction は %d 件ありますが、expense はありません（代表ID: %s）。実支出なら expense 化候補です。',
                $relatedTransactions->count(),
                $relatedTransactions->pluck('id')->take(3)->implode(', '),
            );
        }

        return 'shopping 系キーワードを含む transfer です。同日同額の expense はありません。実支出なら expense 化候補です。口座間移動として残す場合は category/subcategory を空にしてください。';
    }

    private function relatedTransactionSummary(?Transaction $transaction): string
    {
        if (! $transaction instanceof Transaction) {
            return '-';
        }

        return implode(' / ', [
            '#'.$transaction->id,
            $this->displayValue($transaction->account?->name),
            $this->displayValue($transaction->merchant_name),
            $this->displayValue($this->categoryLabel($transaction)),
        ]);
    }

    private function displayValue(mixed $value): string
    {
        if ($value === null) {
            return '-';
        }

        $string = (string) $value;

        return trim($string) === '' ? '-' : $string;
    }

    private function categoryLabel(Transaction $transaction): ?string
    {
        if ($transaction->category === null) {
            return null;
        }

        if ($transaction->subcategory === null) {
            return $transaction->category->name;
        }

        return $transaction->category->name.' > '.$transaction->subcategory->name;
    }

    private function containsPointUsageMarker(Transaction $transaction): bool
    {
        return $this->containsNormalizedTextInValues(
            [
                $transaction->merchant_name,
                $transaction->description,
                $transaction->memo,
            ],
            'ポイント利用分',
        );
    }

    private function cashWithdrawalOrChargeNote(?string $suggestedTransferAccount, ?Transaction $relatedTransfer): string
    {
        if ($relatedTransfer instanceof Transaction) {
            return '同日同額の既存 transfer は、現在口座への請求付替・入金を表す別取引の可能性があります。チャージ元は現在口座のまま、実際の入金先を確認してください。';
        }

        return $suggestedTransferAccount !== null
            ? '現金引き出しやウォレットへのチャージなら expense ではなく transfer が自然です。'
            : '現金引き出しやウォレットへのチャージなら expense ではなく transfer が自然です。相手口座の追加または alias を確認してください。';
    }

    private function normalizedAmount(Transaction $transaction): string
    {
        return number_format((float) $transaction->amount, 2, '.', '');
    }

    private function reasonPriority(string $reason): int
    {
        return match ($reason) {
            'カード引落が expense として記録されている候補' => 10,
            'コード決済請求付替が expense として記録されている候補' => 20,
            '現金引き出し/チャージが expense として記録されている候補' => 25,
            '積立フローの二重表現候補' => 30,
            'カテゴリ付き transfer の確認候補' => 40,
            'shopping 系 transfer の確認候補' => 50,
            '未分類カテゴリ実体の確認候補' => 60,
            default => 100,
        };
    }

    private function normalizeText(string $value): string
    {
        return Str::lower(trim(mb_convert_kana($value, 'asKV', 'UTF-8')));
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

    /**
     * @param  array<int, mixed>  $values
     */
    private function containsNormalizedTextInValues(array $values, string $needle): bool
    {
        $haystack = mb_convert_kana(implode(' ', array_filter(
            $values,
            fn ($value): bool => is_string($value) && trim($value) !== '',
        )), 'asKV', 'UTF-8');
        $haystack = preg_replace('/\s+/u', '', $haystack) ?? $haystack;

        $normalizedNeedle = mb_convert_kana($needle, 'asKV', 'UTF-8');
        $normalizedNeedle = preg_replace('/\s+/u', '', $normalizedNeedle) ?? $normalizedNeedle;

        return Str::contains(Str::lower($haystack), Str::lower($normalizedNeedle));
    }
}
