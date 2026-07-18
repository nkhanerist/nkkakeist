<?php

namespace App\Console\Commands;

use App\Services\Diagnostics\DiagnoseTransactionsService;
use Illuminate\Console\Command;

class DiagnoseTransactionInconsistencies extends Command
{
    private const FORMAT_TABLE = 'table';

    private const FORMAT_TSV = 'tsv';

    private const FORMAT_MARKDOWN = 'markdown';

    private const HEADERS = [
        'transaction_id',
        'user_id',
        'date',
        'type',
        'account',
        'transfer_account',
        'amount',
        'currency',
        'merchant_name',
        'description',
        'memo',
        '診断理由',
    ];

    protected $signature = 'transactions:diagnose
        {--user= : 特定 user id に絞る}
        {--limit=50 : 出力件数}
        {--reason= : 特定診断カテゴリだけ出す}
        {--format=table : 出力形式。table, tsv, markdown のいずれか}';

    protected $description = '既存 transaction データのうち、現在の運用ルールと矛盾している可能性があるものを診断します。';

    public function __construct(
        private readonly DiagnoseTransactionsService $diagnoseTransactionsService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $userId = $this->option('user');
        $limit = (int) $this->option('limit');
        $reason = $this->option('reason');
        $format = (string) $this->option('format');

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

        if (! in_array($format, [self::FORMAT_TABLE, self::FORMAT_TSV, self::FORMAT_MARKDOWN], true)) {
            $this->error('--format は table, tsv, markdown のいずれかを指定してください。');

            return self::INVALID;
        }

        $candidates = $this->diagnoseTransactionsService->handle(
            $userId !== null ? (int) $userId : null,
            is_string($reason) && $reason !== '' ? $reason : null,
            $limit,
        );

        if ($candidates->isEmpty() && $format === self::FORMAT_TABLE) {
            $this->info('診断候補は見つかりませんでした。');

            return self::SUCCESS;
        }

        $rows = $candidates->map(fn (array $candidate): array => $this->formatTableRow($candidate))->all();

        match ($format) {
            self::FORMAT_TABLE => $this->renderTable($rows, $candidates->count()),
            self::FORMAT_TSV => $this->renderTsv($rows),
            self::FORMAT_MARKDOWN => $this->renderMarkdown($rows),
            default => null,
        };

        return self::SUCCESS;
    }

    /**
     * @param  array<int, array<int, string>>  $rows
     */
    private function renderTable(array $rows, int $count): void
    {
        $this->table(self::HEADERS, $rows);

        $this->info('診断候補件数: '.$count);
    }

    /**
     * @param  array<int, array<int, string>>  $rows
     */
    private function renderTsv(array $rows): void
    {
        $this->line(implode("\t", self::HEADERS));

        foreach ($rows as $row) {
            $this->line(implode("\t", array_map(fn (string $value): string => $this->formatTsvValue($value), $row)));
        }
    }

    /**
     * @param  array<int, array<int, string>>  $rows
     */
    private function renderMarkdown(array $rows): void
    {
        $this->line('| '.implode(' | ', array_map(fn (string $value): string => $this->formatMarkdownValue($value), self::HEADERS)).' |');
        $this->line('| '.implode(' | ', array_fill(0, count(self::HEADERS), '---')).' |');

        foreach ($rows as $row) {
            $this->line('| '.implode(' | ', array_map(fn (string $value): string => $this->formatMarkdownValue($value), $row)).' |');
        }
    }

    /**
     * @param  array<string, mixed>  $candidate
     * @return array<int, string>
     */
    private function formatTableRow(array $candidate): array
    {
        return [
            $this->displayValue(data_get($candidate, 'transaction_id')),
            $this->displayValue(data_get($candidate, 'user_id')),
            $this->displayValue(data_get($candidate, 'transaction_date')),
            $this->displayValue(data_get($candidate, 'type')),
            $this->displayValue(data_get($candidate, 'account_name')),
            $this->displayValue(data_get($candidate, 'transfer_account_name')),
            $this->displayValue(data_get($candidate, 'amount')),
            $this->displayValue(data_get($candidate, 'currency')),
            $this->displayValue(data_get($candidate, 'merchant_name')),
            $this->displayValue(data_get($candidate, 'description')),
            $this->displayValue(data_get($candidate, 'memo')),
            $this->displayValue(data_get($candidate, 'suspicion_reason')),
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
