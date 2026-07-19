<?php

namespace App\Services\Imports;

use DateTimeImmutable;
use RuntimeException;

class MoneyForwardAssetHistoryCsvParser
{
    /**
     * @return array{rows: array<int, array<string, mixed>>, metadata: array<string, mixed>}
     */
    public function parse(string $contents): array
    {
        $rows = $this->readRows($this->convertToUtf8($contents));

        if ($rows === [] || count($rows[0]) === 0) {
            throw new RuntimeException(trans('imports.parse_errors.asset_history_header_unreadable'));
        }

        $headers = array_map(fn (string $header): string => $this->normalizeHeader($header), $rows[0]);
        $dateIndex = array_search('日付', $headers, true);
        $totalIndex = array_search('合計', $headers, true);

        if ($dateIndex === false || $totalIndex === false) {
            throw new RuntimeException(trans('imports.parse_errors.asset_history_required_headers'));
        }

        $parsedRows = [];

        foreach (array_slice($rows, 1) as $index => $row) {
            if ($this->isEmptyRow($row)) {
                continue;
            }

            $date = $this->date($row[$dateIndex] ?? '');
            $totalAssets = $this->amount($row[$totalIndex] ?? '');

            if ($date === null || $totalAssets === null) {
                throw new RuntimeException(trans('imports.parse_errors.asset_history_row_invalid', [
                    'row' => $index + 2,
                ]));
            }

            $breakdown = [];

            foreach ($headers as $columnIndex => $label) {
                if ($columnIndex === $dateIndex || $columnIndex === $totalIndex || $label === '') {
                    continue;
                }

                $value = $this->amount($row[$columnIndex] ?? '');

                if ($value !== null) {
                    $breakdown[$label] = $value;
                }
            }

            $parsedRows[] = [
                'row_number' => $index + 2,
                'raw_payload' => [
                    'source' => 'money_forward',
                    'captured_on' => $date,
                    'total_assets' => $totalAssets,
                    'currency' => 'JPY',
                    'breakdown' => $breakdown,
                ],
                'transaction_date' => $date,
                'amount' => $totalAssets,
                'merchant_name' => 'Money Forward',
                'description' => '資産推移',
                'account_name' => null,
                'category_name' => null,
                'subcategory_name' => null,
                'detected_type' => 'unknown',
                'category_id' => null,
                'subcategory_id' => null,
                'duplicate_hash' => null,
                'is_calculation_target' => false,
                'affects_account_balance' => false,
            ];
        }

        if ($parsedRows === []) {
            throw new RuntimeException(trans('imports.parse_errors.asset_history_no_rows'));
        }

        return [
            'rows' => $parsedRows,
            'metadata' => [
                'format' => 'money_forward_asset_history_csv',
                'version' => 1,
                'source' => 'money_forward',
            ],
        ];
    }

    private function convertToUtf8(string $contents): string
    {
        $encoding = mb_detect_encoding($contents, ['UTF-8', 'SJIS-win', 'CP932', 'EUC-JP'], true) ?: 'SJIS-win';

        return mb_convert_encoding($contents, 'UTF-8', $encoding);
    }

    /** @return array<int, array<int, string>> */
    private function readRows(string $contents): array
    {
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $contents);
        rewind($stream);
        $rows = [];

        while (($row = fgetcsv($stream)) !== false) {
            $rows[] = array_map(fn ($value): string => trim((string) $value), $row);
        }

        fclose($stream);

        return $rows;
    }

    private function normalizeHeader(string $header): string
    {
        $header = preg_replace('/^\x{FEFF}/u', '', $header) ?? $header;
        $header = mb_convert_kana($header, 'asKV', 'UTF-8');
        $header = preg_replace('/[\s　]+/u', '', $header) ?? $header;
        $header = preg_replace('/[（(]円[）)]$/u', '', $header) ?? $header;

        return trim($header);
    }

    /** @param array<int, string> $row */
    private function isEmptyRow(array $row): bool
    {
        return collect($row)->every(fn (string $value): bool => trim($value) === '');
    }

    private function date(string $value): ?string
    {
        foreach (['Y/m/d', 'Y-m-d'] as $format) {
            $date = DateTimeImmutable::createFromFormat('!'.$format, trim($value));
            $errors = DateTimeImmutable::getLastErrors();

            if ($date !== false && ($errors === false || (($errors['warning_count'] ?? 0) === 0 && ($errors['error_count'] ?? 0) === 0))) {
                return $date->format('Y-m-d');
            }
        }

        return null;
    }

    private function amount(string $value): ?string
    {
        $normalized = str_replace([',', '円', '¥', ' '], '', trim($value));

        if ($normalized === '' || preg_match('/^[+-]?\d{1,12}(?:\.\d{1,2})?$/', $normalized) !== 1) {
            return null;
        }

        $isNegative = str_starts_with($normalized, '-');
        $normalized = ltrim($normalized, '+-');
        [$whole, $fraction] = array_pad(explode('.', $normalized, 2), 2, '0');

        return sprintf('%s%d.%s', $isNegative ? '-' : '', (int) $whole, str_pad($fraction, 2, '0'));
    }
}
