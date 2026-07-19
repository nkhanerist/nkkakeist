<?php

namespace App\Services\Imports;

use RuntimeException;

class ImportParserService
{
    public function __construct(
        private readonly MoneyForwardCsvParser $moneyForwardCsvParser,
        private readonly MobileSuicaPdfParser $mobileSuicaPdfParser,
        private readonly JrePointJsonParser $jrePointJsonParser,
        private readonly BalanceSnapshotJsonParser $balanceSnapshotJsonParser,
        private readonly MoneyForwardAssetHistoryCsvParser $moneyForwardAssetHistoryCsvParser,
    ) {}

    /**
     * @return array{rows: array<int, array<string, mixed>>, metadata: array<string, mixed>|null}
     */
    public function parse(string $sourceName, string $contents): array
    {
        return match ($sourceName) {
            'money_forward' => ['rows' => $this->moneyForwardCsvParser->parse($contents), 'metadata' => null],
            'mobile_suica' => ['rows' => $this->mobileSuicaPdfParser->parse($contents), 'metadata' => null],
            'jre_point' => $this->jrePointJsonParser->parse($contents),
            'balance_snapshot' => $this->balanceSnapshotJsonParser->parse($contents),
            'asset_history' => $this->moneyForwardAssetHistoryCsvParser->parse($contents),
            default => throw new RuntimeException(trans('imports.parse_errors.unsupported_format')),
        };
    }
}
