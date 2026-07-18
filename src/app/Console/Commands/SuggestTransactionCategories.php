<?php

namespace App\Console\Commands;

use App\Services\Diagnostics\SuggestTransactionCategoriesService;
use Illuminate\Console\Command;

class SuggestTransactionCategories extends Command
{
    private const FORMAT_TABLE = 'table';

    private const FORMAT_TSV = 'tsv';

    private const FORMAT_MARKDOWN = 'markdown';

    private const HEADERS = [
        'transaction_id',
        'user_id',
        '種別',
        '日付',
        '金額',
        '通貨',
        '口座',
        '摘要',
        '説明',
        '現在カテゴリ',
        '現在サブカテゴリ',
        '推定カテゴリ',
        '推定サブカテゴリ',
        '信頼度',
        '推定理由',
        '参照件数',
        '代表ID',
        'ルールID',
    ];

    protected $signature = 'transactions:suggest-categories
        {--user= : 特定 user id に絞る}
        {--year= : 特定年に絞る}
        {--type=all : 対象種別。all, expense, income のいずれか}
        {--min-confidence=70 : 出力する最小信頼度}
        {--mode=suggested : 出力対象。suggested, missing, all のいずれか}
        {--limit=50 : 出力件数}
        {--format=table : 出力形式。table, tsv, markdown のいずれか}';

    protected $description = 'カテゴリ未設定の transaction に対して、既存ルールと分類済み履歴からカテゴリ候補を read-only で表示します。';

    public function __construct(
        private readonly SuggestTransactionCategoriesService $suggestTransactionCategoriesService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $userId = $this->option('user');
        $year = $this->option('year');
        $type = (string) $this->option('type');
        $minConfidenceOption = $this->option('min-confidence');
        $mode = (string) $this->option('mode');
        $limit = (int) $this->option('limit');
        $format = (string) $this->option('format');

        if ($userId !== null && (! is_numeric($userId) || (int) $userId <= 0)) {
            $this->error('--user は正の整数で指定してください。');

            return self::INVALID;
        }

        if ($year !== null && (! is_numeric($year) || (int) $year < 2000 || (int) $year > 2100)) {
            $this->error('--year は 2000 から 2100 の整数で指定してください。');

            return self::INVALID;
        }

        if (! in_array($type, ['all', 'expense', 'income'], true)) {
            $this->error('--type は all, expense, income のいずれかを指定してください。');

            return self::INVALID;
        }

        if (! is_numeric($minConfidenceOption)) {
            $this->error('--min-confidence は 0 から 100 の整数で指定してください。');

            return self::INVALID;
        }

        $minConfidence = (int) $minConfidenceOption;

        if ($minConfidence < 0 || $minConfidence > 100) {
            $this->error('--min-confidence は 0 から 100 の整数で指定してください。');

            return self::INVALID;
        }

        if (! in_array($mode, ['suggested', 'missing', 'all'], true)) {
            $this->error('--mode は suggested, missing, all のいずれかを指定してください。');

            return self::INVALID;
        }

        if ($limit <= 0) {
            $this->error('--limit は 1 以上で指定してください。');

            return self::INVALID;
        }

        if (! in_array($format, [self::FORMAT_TABLE, self::FORMAT_TSV, self::FORMAT_MARKDOWN], true)) {
            $this->error('--format は table, tsv, markdown のいずれかを指定してください。');

            return self::INVALID;
        }

        $suggestions = $this->suggestTransactionCategoriesService->handle(
            $userId !== null ? (int) $userId : null,
            $year !== null ? (int) $year : null,
            $type,
            $minConfidence,
            $limit,
            $mode,
        );

        if ($suggestions->isEmpty()) {
            $this->info($mode === 'missing' ? '未提案のカテゴリ未設定 transaction は見つかりませんでした。' : 'カテゴリ提案は見つかりませんでした。');

            return self::SUCCESS;
        }

        $rows = $suggestions
            ->map(fn (array $suggestion): array => $this->formatRow($suggestion))
            ->all();

        match ($format) {
            self::FORMAT_TABLE => $this->renderTable(self::HEADERS, $rows, $suggestions->count()),
            self::FORMAT_TSV => $this->renderTsv(self::HEADERS, $rows),
            self::FORMAT_MARKDOWN => $this->renderMarkdown(self::HEADERS, $rows),
            default => null,
        };

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $suggestion
     * @return array<int, string>
     */
    private function formatRow(array $suggestion): array
    {
        return [
            $this->displayValue(data_get($suggestion, 'transaction_id')),
            $this->displayValue(data_get($suggestion, 'user_id')),
            $this->displayValue(data_get($suggestion, 'type')),
            $this->displayValue(data_get($suggestion, 'transaction_date')),
            $this->displayValue(data_get($suggestion, 'amount')),
            $this->displayValue(data_get($suggestion, 'currency')),
            $this->displayValue(data_get($suggestion, 'account_name')),
            $this->displayValue(data_get($suggestion, 'merchant_name')),
            $this->displayValue(data_get($suggestion, 'description')),
            $this->displayValue(data_get($suggestion, 'current_category')),
            $this->displayValue(data_get($suggestion, 'current_subcategory')),
            $this->displayValue(data_get($suggestion, 'suggested_category')),
            $this->displayValue(data_get($suggestion, 'suggested_subcategory')),
            $this->displayValue(data_get($suggestion, 'confidence')),
            $this->displayValue(data_get($suggestion, 'reason')),
            $this->displayValue(data_get($suggestion, 'reference_count')),
            $this->displayValue(data_get($suggestion, 'reference_transaction_id')),
            $this->displayValue(data_get($suggestion, 'matched_classification_rule_id')),
        ];
    }

    /**
     * @param  array<int, string>  $headers
     * @param  array<int, array<int, string>>  $rows
     */
    private function renderTable(array $headers, array $rows, int $count): void
    {
        $this->table($headers, $rows);
        $this->info('カテゴリ提案件数: '.$count);
    }

    /**
     * @param  array<int, string>  $headers
     * @param  array<int, array<int, string>>  $rows
     */
    private function renderTsv(array $headers, array $rows): void
    {
        $this->line(implode("\t", $headers));

        foreach ($rows as $row) {
            $this->line(implode("\t", array_map(fn (string $value): string => $this->formatFlatValue($value), $row)));
        }
    }

    /**
     * @param  array<int, string>  $headers
     * @param  array<int, array<int, string>>  $rows
     */
    private function renderMarkdown(array $headers, array $rows): void
    {
        $this->line('| '.implode(' | ', array_map(fn (string $value): string => $this->formatMarkdownValue($value), $headers)).' |');
        $this->line('| '.implode(' | ', array_fill(0, count($headers), '---')).' |');

        foreach ($rows as $row) {
            $this->line('| '.implode(' | ', array_map(fn (string $value): string => $this->formatMarkdownValue($value), $row)).' |');
        }
    }

    private function displayValue(mixed $value): string
    {
        if ($value === null) {
            return '-';
        }

        $string = (string) $value;

        return trim($string) === '' ? '-' : $string;
    }

    private function formatFlatValue(string $value): string
    {
        return preg_replace('/\s+/', ' ', $value) ?? $value;
    }

    private function formatMarkdownValue(string $value): string
    {
        return str_replace('|', '\\|', $this->formatFlatValue($value));
    }
}
