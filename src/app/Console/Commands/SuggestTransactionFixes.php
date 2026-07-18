<?php

namespace App\Console\Commands;

use App\Services\Diagnostics\DiagnoseTransactionsService;
use App\Services\Diagnostics\SuggestTransactionFixesService;
use Illuminate\Console\Command;

class SuggestTransactionFixes extends Command
{
    private const FORMAT_TABLE = 'table';

    private const FORMAT_TSV = 'tsv';

    private const FORMAT_MARKDOWN = 'markdown';

    private const HEADERS = [
        'transaction_id',
        'user_id',
        '現在種別',
        '提案操作',
        '提案種別',
        '提案元口座',
        '提案相手口座',
        '診断理由',
        '確認メモ',
    ];

    private const DETAIL_HEADERS = [
        '日付',
        '金額',
        '通貨',
        '摘要',
        '説明',
        'メモ',
        '現在カテゴリ',
        '現在サブカテゴリ',
    ];

    protected $signature = 'transactions:suggest-fixes
        {--user= : 特定 user id に絞る}
        {--limit=50 : 出力件数}
        {--reason= : 特定診断カテゴリだけ出す}
        {--action= : 提案操作に完全一致するものだけ出す}
        {--details : 手動補正用に日付・金額・摘要・現在カテゴリなどの詳細列を出す}
        {--format=table : 出力形式。table, tsv, markdown のいずれか}';

    protected $description = '既存 transaction データに対して、このアプリの運用ルールに沿った補正提案を read-only で表示します。';

    public function __construct(
        private readonly SuggestTransactionFixesService $suggestTransactionFixesService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $userId = $this->option('user');
        $limit = (int) $this->option('limit');
        $reason = $this->option('reason');
        $action = $this->option('action');
        $details = (bool) $this->option('details');
        $format = (string) $this->option('format');

        if (is_string($action)) {
            $action = trim($action);
        }

        if ($userId !== null && (! is_numeric($userId) || (int) $userId <= 0)) {
            $this->error('--user は正の整数で指定してください。');

            return self::INVALID;
        }

        if ($limit <= 0) {
            $this->error('--limit は 1 以上で指定してください。');

            return self::INVALID;
        }

        if ($reason !== null && ! in_array($reason, DiagnoseTransactionsService::reasons(), true)) {
            $this->error('--reason は次のいずれかを指定してください: '.implode(', ', DiagnoseTransactionsService::reasons()));

            return self::INVALID;
        }

        if ($action !== null && (! is_string($action) || trim($action) === '')) {
            $this->error('--action は空でない文字列で指定してください。');

            return self::INVALID;
        }

        if (! in_array($format, [self::FORMAT_TABLE, self::FORMAT_TSV, self::FORMAT_MARKDOWN], true)) {
            $this->error('--format は table, tsv, markdown のいずれかを指定してください。');

            return self::INVALID;
        }

        $suggestions = $this->suggestTransactionFixesService->handle(
            $userId !== null ? (int) $userId : null,
            is_string($reason) && $reason !== '' ? $reason : null,
            $limit,
            is_string($action) && $action !== '' ? $action : null,
        );

        if ($suggestions->isEmpty()) {
            $this->info('補正提案は見つかりませんでした。');

            return self::SUCCESS;
        }

        $headers = $this->headers($details);
        $rows = $suggestions->map(fn (array $suggestion): array => $this->formatTableRow($suggestion, $details))->all();

        match ($format) {
            self::FORMAT_TABLE => $this->renderTable($headers, $rows, $suggestions->count()),
            self::FORMAT_TSV => $this->renderTsv($headers, $rows),
            self::FORMAT_MARKDOWN => $this->renderMarkdown($headers, $rows),
            default => null,
        };

        return self::SUCCESS;
    }

    /**
     * @return array<int, string>
     */
    private function headers(bool $details): array
    {
        return $details ? array_merge(self::HEADERS, self::DETAIL_HEADERS) : self::HEADERS;
    }

    /**
     * @param  array<int, string>  $headers
     * @param  array<int, array<int, string>>  $rows
     */
    private function renderTable(array $headers, array $rows, int $count): void
    {
        $this->table($headers, $rows);

        $this->info('補正提案件数: '.$count);
    }

    /**
     * @param  array<int, string>  $headers
     * @param  array<int, array<int, string>>  $rows
     */
    private function renderTsv(array $headers, array $rows): void
    {
        $this->line(implode("\t", $headers));

        foreach ($rows as $row) {
            $this->line(implode("\t", array_map(fn (string $value): string => $this->formatTsvValue($value), $row)));
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

    /**
     * @param  array<string, mixed>  $suggestion
     * @return array<int, string>
     */
    private function formatTableRow(array $suggestion, bool $details): array
    {
        $row = [
            $this->displayValue(data_get($suggestion, 'transaction_id')),
            $this->displayValue(data_get($suggestion, 'user_id')),
            $this->displayValue(data_get($suggestion, 'current_type')),
            $this->displayValue(data_get($suggestion, 'suggested_action')),
            $this->displayValue(data_get($suggestion, 'suggested_type')),
            $this->displayValue(data_get($suggestion, 'suggested_source_account')),
            $this->displayValue(data_get($suggestion, 'suggested_transfer_account')),
            $this->displayValue(data_get($suggestion, 'reason')),
            $this->displayValue(data_get($suggestion, 'note')),
        ];

        if (! $details) {
            return $row;
        }

        return [
            ...$row,
            $this->displayValue(data_get($suggestion, 'transaction_date')),
            $this->displayValue(data_get($suggestion, 'amount')),
            $this->displayValue(data_get($suggestion, 'currency')),
            $this->displayValue(data_get($suggestion, 'merchant_name')),
            $this->displayValue(data_get($suggestion, 'description')),
            $this->displayValue(data_get($suggestion, 'memo')),
            $this->displayValue(data_get($suggestion, 'current_category')),
            $this->displayValue(data_get($suggestion, 'current_subcategory')),
        ];
    }

    private function displayValue(mixed $value): string
    {
        if ($value === null) {
            return '-';
        }

        $string = (string) $value;

        return trim($string) === '' ? '-' : $string;
    }

    private function formatTsvValue(string $value): string
    {
        return preg_replace('/\s+/', ' ', $value) ?? $value;
    }

    private function formatMarkdownValue(string $value): string
    {
        $normalized = preg_replace('/\s+/', ' ', $value) ?? $value;

        return str_replace('|', '\\|', $normalized);
    }
}
