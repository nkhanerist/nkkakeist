<?php

namespace App\Console\Commands;

use App\Services\Diagnostics\DiagnoseTransactionsService;
use Illuminate\Console\Command;

class SummarizeTransactionDiagnostics extends Command
{
    protected $signature = 'transactions:diagnose-summary
        {--user= : 特定 user id に絞る}
        {--only-nonzero : 件数が 1 件以上の診断理由だけを表示する}';

    protected $description = '既存 transaction データの診断候補を理由別に read-only で集計します。';

    public function __construct(
        private readonly DiagnoseTransactionsService $diagnoseTransactionsService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $userId = $this->option('user');

        if ($userId !== null && (! is_numeric($userId) || (int) $userId <= 0)) {
            $this->error('--user は正の整数で指定してください。');

            return self::INVALID;
        }

        $summary = $this->diagnoseTransactionsService->summary(
            $userId !== null ? (int) $userId : null,
        );

        if ((bool) $this->option('only-nonzero')) {
            $summary = $summary
                ->filter(fn (array $row): bool => (int) data_get($row, 'count', 0) > 0)
                ->values();
        }

        if ($summary->isEmpty()) {
            $this->info('診断候補はありません。');

            return self::SUCCESS;
        }

        $this->table(
            ['診断理由', '件数', '最新日', '最古日'],
            $summary->map(fn (array $row): array => $this->formatTableRow($row))->all(),
        );

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<int, string>
     */
    private function formatTableRow(array $row): array
    {
        return [
            $this->displayValue(data_get($row, 'suspicion_reason')),
            $this->displayValue(data_get($row, 'count')),
            $this->displayValue(data_get($row, 'latest_date')),
            $this->displayValue(data_get($row, 'oldest_date')),
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
}
