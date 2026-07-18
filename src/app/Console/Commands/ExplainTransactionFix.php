<?php

namespace App\Console\Commands;

use App\Models\Transaction;
use App\Services\Diagnostics\SuggestTransactionFixesService;
use Illuminate\Console\Command;

class ExplainTransactionFix extends Command
{
    protected $signature = 'transactions:explain-fix
        {transaction : 詳細確認する transaction id}';

    protected $description = '指定 transaction に対する補正提案を read-only で詳しく表示します。';

    public function __construct(
        private readonly SuggestTransactionFixesService $suggestTransactionFixesService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $transactionId = $this->argument('transaction');

        if (
            (! is_int($transactionId) && ! is_string($transactionId))
            || ! preg_match('/^[1-9][0-9]*$/', (string) $transactionId)
        ) {
            $this->error('transaction は正の整数で指定してください。');

            return self::INVALID;
        }

        $transaction = Transaction::query()
            ->with(['account', 'transferAccount', 'category', 'subcategory'])
            ->find((int) $transactionId);

        if (! $transaction instanceof Transaction) {
            $this->error('指定された transaction は見つかりませんでした。');

            return self::FAILURE;
        }

        $this->info('取引情報');
        $this->table(['項目', '値'], $this->transactionRows($transaction));

        $suggestions = $this->suggestTransactionFixesService->explain($transaction);

        if ($suggestions->isEmpty()) {
            $this->info('この transaction に対する補正提案は見つかりませんでした。');

            return self::SUCCESS;
        }

        foreach ($suggestions as $index => $suggestion) {
            $this->info($suggestions->count() === 1 ? '補正提案' : '補正提案 '.($index + 1));
            $this->table(['項目', '値'], [
                ['診断理由', $this->displayValue(data_get($suggestion, 'reason'))],
                ['提案操作', $this->displayValue(data_get($suggestion, 'suggested_action'))],
                ['提案種別', $this->displayValue(data_get($suggestion, 'suggested_type'))],
                ['提案元口座', $this->displayValue(data_get($suggestion, 'suggested_source_account'))],
                ['提案相手口座', $this->displayValue(data_get($suggestion, 'suggested_transfer_account'))],
                ['確認メモ', $this->displayValue(data_get($suggestion, 'note'))],
            ]);

            $this->info($suggestions->count() === 1 ? '手動修正時の確認' : '手動修正時の確認 '.($index + 1));
            foreach ($this->manualCheckItems((string) data_get($suggestion, 'reason'), $suggestion) as $item) {
                $this->line('- '.$item);
            }

            if (data_get($suggestion, 'reason') === 'shopping 系 transfer の確認候補') {
                $this->outputShoppingRelatedTransactions($transaction);
            }
        }

        return self::SUCCESS;
    }

    /**
     * @return array<int, array<int, string>>
     */
    private function transactionRows(Transaction $transaction): array
    {
        return [
            ['transaction_id', (string) $transaction->id],
            ['user_id', (string) $transaction->user_id],
            ['date', $this->displayValue($transaction->transaction_date?->format('Y-m-d'))],
            ['type', $this->displayValue($transaction->type)],
            ['account', $this->displayValue($transaction->account?->name)],
            ['transfer_account', $this->displayValue($transaction->transferAccount?->name)],
            ['amount', $this->displayValue($transaction->amount)],
            ['currency', $this->displayValue($transaction->currency)],
            ['merchant_name', $this->displayValue($transaction->merchant_name)],
            ['description', $this->displayValue($transaction->description)],
            ['memo', $this->displayValue($transaction->memo)],
            ['category', $this->displayValue($transaction->category?->name)],
            ['subcategory', $this->displayValue($transaction->subcategory?->name)],
            ['is_calculation_target', $transaction->is_calculation_target ? 'true' : 'false'],
        ];
    }

    /**
     * @return array<int, string>
     */
    private function manualCheckItems(string $reason, array $suggestion): array
    {
        $items = match ($reason) {
            'カード引落が expense として記録されている候補' => [
                '同じカード利用本体の expense が別に存在するか確認する',
                'カード引落なら type=transfer、source=銀行口座、transfer_account=クレジットカード口座へ修正する',
                'is_calculation_target=false、category/subcategory なしにする',
            ],
            'コード決済請求付替が expense として記録されている候補' => [
                '実際の消費 expense が別に存在するか確認する',
                '同日同額・同一摘要の重複取込ではないか確認する',
                '請求付替なら type=transfer にし、source / destination を実際の請求方向に合わせる',
                'is_calculation_target=false、category/subcategory なしにする',
            ],
            '現金引き出し/チャージが expense として記録されている候補' => [
                '銀行口座から現金への引き出し、または電子マネー口座へのチャージではないか確認する',
                '引き出しやチャージなら type=transfer にし、source / destination を実際の資金移動方向に合わせる',
                'is_calculation_target=false、category/subcategory なしにする',
                '提案相手口座が空の場合は、現金・PayPay・Suica などの口座追加または accounts.import_aliases を確認する',
            ],
            '積立フローの二重表現候補' => [
                '同日同額の積立 transfer が同じ実態を重複表現していないか確認する',
                'd払い経由 / dcard 直 / 銀行直 のどれを採用するか決める',
                '重複なら片方を削除または修正し、is_calculation_target=false を維持する',
            ],
            'カテゴリ付き transfer の確認候補' => [
                '口座間移動なのか実際の消費なのか確認する',
                '口座間移動なら category/subcategory を外す',
                '実際の消費なら type=expense への修正を検討する',
            ],
            'shopping 系 transfer の確認候補' => [
                '同日同額の実際の消費 expense が別に存在するか確認する',
                '同日同額の expense がなければ、この transfer を type=expense に直すべき可能性がある',
                '同日同額の expense があれば、二重計上を避けるため transfer と expense のどちらを残すべきか確認する',
                '口座間移動なら type=transfer のまま category/subcategory なしにする',
                'transfer として残す場合は is_calculation_target=false を維持する',
            ],
            '未分類カテゴリ実体の確認候補' => $this->uncategorizedCategoryCheckItems($suggestion),
            default => [
                '診断理由に応じて transaction 内容を確認する',
            ],
        };

        if (
            in_array($reason, [
                'カード引落が expense として記録されている候補',
                'コード決済請求付替が expense として記録されている候補',
            ], true)
            && data_get($suggestion, 'suggested_transfer_account') === null
        ) {
            $items[] = '提案相手口座が空の場合は、口座名と accounts.import_aliases の不足を確認する';
        }

        return $items;
    }

    private function displayValue(mixed $value): string
    {
        if ($value === null) {
            return '-';
        }

        $string = (string) $value;

        return trim($string) === '' ? '-' : $string;
    }

    private function outputShoppingRelatedTransactions(Transaction $transaction): void
    {
        $this->info('同日同額の関連候補');

        $rows = $this->relatedTransactionRows($transaction);

        if ($rows === []) {
            $this->line('同日同額の関連候補は見つかりませんでした。');

            return;
        }

        $expenseCount = collect($rows)
            ->filter(fn (array $row): bool => $row[1] === 'expense')
            ->count();

        $this->line(sprintf(
            '関連候補 %d 件 / expense %d 件',
            count($rows),
            $expenseCount,
        ));

        foreach ($rows as $row) {
            $this->line(sprintf(
                '- #%s %s / %s -> %s / %s / %s / %s / %s / 集計対象:%s',
                $row[0],
                $row[1],
                $row[2],
                $row[3],
                $row[4],
                $row[5],
                $row[6],
                $row[7],
                $row[8],
            ));
        }

        $this->table([
            'id',
            'type',
            'account',
            'transfer_account',
            'merchant_name',
            'description',
            'memo',
            'category',
            'is_calculation_target',
        ], $rows);
    }

    /**
     * @return array<int, array<int, string>>
     */
    private function relatedTransactionRows(Transaction $transaction): array
    {
        if ($transaction->transaction_date === null) {
            return [];
        }

        $transactionDate = $transaction->getRawOriginal('transaction_date');
        $amount = $transaction->getRawOriginal('amount');

        return Transaction::query()
            ->with(['account', 'transferAccount', 'category', 'subcategory'])
            ->where('user_id', $transaction->user_id)
            ->whereKeyNot($transaction->id)
            ->where('transaction_date', $transactionDate)
            ->where('amount', $amount)
            ->where('currency', $transaction->currency)
            ->orderBy('id')
            ->get()
            ->filter(fn (Transaction $relatedTransaction): bool => $this->normalizedAmount($relatedTransaction) === $this->normalizedAmount($transaction))
            ->take(20)
            ->map(fn (Transaction $relatedTransaction): array => [
                (string) $relatedTransaction->id,
                $this->displayValue($relatedTransaction->type),
                $this->displayValue($relatedTransaction->account?->name),
                $this->displayValue($relatedTransaction->transferAccount?->name),
                $this->displayValue($relatedTransaction->merchant_name),
                $this->displayValue($relatedTransaction->description),
                $this->displayValue($relatedTransaction->memo),
                $this->displayValue($this->categoryLabel($relatedTransaction)),
                $relatedTransaction->is_calculation_target ? 'true' : 'false',
            ])
            ->all();
    }

    private function normalizedAmount(Transaction $transaction): string
    {
        return number_format((float) $transaction->amount, 2, '.', '');
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

    /**
     * @param  array<string, mixed>  $suggestion
     * @return array<int, string>
     */
    private function uncategorizedCategoryCheckItems(array $suggestion): array
    {
        if (data_get($suggestion, 'suggested_action') === 'サブカテゴリ未設定へ見直し確認') {
            return [
                '実サブカテゴリへ分類すべき取引ではないか確認する',
                '本当に未分類サブカテゴリなら category は残し、subcategory だけ外して subcategory_id=null にする',
                '取引種別と is_calculation_target は原則として変更しない',
            ];
        }

        return [
            '実カテゴリへ分類すべき取引ではないか確認する',
            '本当に未分類なら category/subcategory を外して category_id=null / subcategory_id=null にする',
            '取引種別と is_calculation_target は原則として変更しない',
        ];
    }
}
