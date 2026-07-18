<?php

namespace App\Services\Imports;

use RuntimeException;

class MoneyForwardCsvParser
{
    /**
     * @return array<int, array{
     *     row_number: int,
     *     raw_payload: array<string, string>,
     *     transaction_date: string|null,
     *     amount: string|null,
     *     account_name: string|null,
     *     category_name: string|null,
     *     subcategory_name: string|null,
     *     merchant_name: string|null,
     *     description: string|null,
     *     detected_type: string|null,
     *     is_calculation_target: bool|null
     * }>
     */
    public function parse(string $contents): array
    {
        $rows = $this->readRows($this->convertToUtf8($contents));

        if ($rows === [] || count($rows[0]) === 0) {
            throw new RuntimeException('CSV のヘッダ行を読み取れませんでした。');
        }

        $headerMap = $this->buildHeaderMap($rows[0]);
        $this->assertRequiredHeaders($headerMap);

        $parsedRows = [];

        foreach (array_slice($rows, 1) as $index => $row) {
            if ($this->isEmptyRow($row)) {
                continue;
            }

            $rawPayload = $this->extractRawPayload($rows[0], $row);
            $amountInfo = $this->parseAmount($this->field($row, $headerMap, 'amount'));

            $parsedRows[] = [
                'row_number' => $index + 2,
                'raw_payload' => $rawPayload,
                'transaction_date' => $this->parseDate($this->field($row, $headerMap, 'date')),
                'amount' => $amountInfo['amount'],
                'account_name' => $this->nullableString($this->field($row, $headerMap, 'institution')),
                'category_name' => $this->nullableCategoryString($this->field($row, $headerMap, 'major_category')),
                'subcategory_name' => $this->nullableCategoryString($this->field($row, $headerMap, 'minor_category')),
                'merchant_name' => $this->nullableString($this->field($row, $headerMap, 'content')),
                'description' => $this->buildDescription(
                    $this->field($row, $headerMap, 'institution'),
                    $this->field($row, $headerMap, 'major_category'),
                    $this->field($row, $headerMap, 'minor_category'),
                    $this->field($row, $headerMap, 'memo'),
                ),
                'detected_type' => $this->detectType(
                    $amountInfo['is_negative'],
                    $amountInfo['amount'],
                    $this->field($row, $headerMap, 'transfer'),
                ),
                'is_calculation_target' => $this->parseCalculationTarget(
                    $this->field($row, $headerMap, 'calculation_target'),
                ),
                'affects_account_balance' => $this->parseCalculationTarget(
                    $this->field($row, $headerMap, 'calculation_target'),
                ),
            ];
        }

        return $parsedRows;
    }

    private function convertToUtf8(string $contents): string
    {
        $encoding = mb_detect_encoding($contents, ['UTF-8', 'SJIS-win', 'CP932', 'EUC-JP'], true) ?: 'SJIS-win';

        return mb_convert_encoding($contents, 'UTF-8', $encoding);
    }

    /**
     * @return array<int, array<int, string>>
     */
    private function readRows(string $contents): array
    {
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $contents);
        rewind($stream);

        $rows = [];

        while (($row = fgetcsv($stream)) !== false) {
            $rows[] = array_map(
                fn ($value): string => trim((string) $value),
                $row,
            );
        }

        fclose($stream);

        return $rows;
    }

    /**
     * @param  array<int, string>  $headers
     * @return array<string, int>
     */
    private function buildHeaderMap(array $headers): array
    {
        $aliases = [
            'calculation_target' => ['計算対象'],
            'date' => ['日付'],
            'content' => ['内容', '摘要'],
            'amount' => ['金額（円）', '金額(円)', '金額'],
            'institution' => ['保有金融機関'],
            'major_category' => ['大項目'],
            'minor_category' => ['中項目'],
            'memo' => ['メモ'],
            'transfer' => ['振替'],
            'id' => ['ID', 'id'],
        ];

        $normalizedHeaders = [];

        foreach ($headers as $index => $header) {
            $normalizedHeaders[$this->normalizeHeader($header)] = $index;
        }

        $headerMap = [];

        foreach ($aliases as $key => $candidates) {
            foreach ($candidates as $candidate) {
                $normalized = $this->normalizeHeader($candidate);

                if (array_key_exists($normalized, $normalizedHeaders)) {
                    $headerMap[$key] = $normalizedHeaders[$normalized];
                    break;
                }
            }
        }

        return $headerMap;
    }

    /**
     * @param  array<string, int>  $headerMap
     */
    private function assertRequiredHeaders(array $headerMap): void
    {
        foreach (['date', 'content', 'amount'] as $requiredHeader) {
            if (! array_key_exists($requiredHeader, $headerMap)) {
                throw new RuntimeException('Money Forward CSV の必須ヘッダが不足しています。');
            }
        }
    }

    /**
     * @param  array<int, string>  $headers
     * @param  array<int, string>  $row
     * @return array<string, string>
     */
    private function extractRawPayload(array $headers, array $row): array
    {
        $payload = [];

        foreach ($headers as $index => $header) {
            $payload[$header] = $row[$index] ?? '';
        }

        return $payload;
    }

    /**
     * @param  array<int, string>  $row
     * @param  array<string, int>  $headerMap
     */
    private function field(array $row, array $headerMap, string $key): string
    {
        if (! array_key_exists($key, $headerMap)) {
            return '';
        }

        return trim((string) ($row[$headerMap[$key]] ?? ''));
    }

    private function normalizeHeader(string $header): string
    {
        $header = preg_replace('/^\x{FEFF}/u', '', $header) ?? $header;
        $normalized = mb_convert_kana($header, 'asKV', 'UTF-8');
        $normalized = preg_replace('/\s+/u', '', $normalized) ?? $normalized;

        return mb_strtolower($normalized, 'UTF-8');
    }

    /**
     * @param  array<int, string>  $row
     */
    private function isEmptyRow(array $row): bool
    {
        foreach ($row as $value) {
            if (trim($value) !== '') {
                return false;
            }
        }

        return true;
    }

    private function parseDate(string $value): ?string
    {
        foreach (['Y/m/d', 'Y-m-d'] as $format) {
            $date = \DateTimeImmutable::createFromFormat($format, $value);
            $errors = \DateTimeImmutable::getLastErrors();

            if (
                $date !== false
                && ($errors === false || (($errors['warning_count'] ?? 0) === 0 && ($errors['error_count'] ?? 0) === 0))
            ) {
                return $date->format('Y-m-d');
            }
        }

        return null;
    }

    /**
     * @return array{amount: string|null, is_negative: bool}
     */
    private function parseAmount(string $value): array
    {
        $normalized = str_replace([',', '円', '¥', ' '], '', trim($value));

        if ($normalized === '' || ! preg_match('/^[+-]?\d+(?:\.\d+)?$/', $normalized)) {
            return ['amount' => null, 'is_negative' => false];
        }

        $isNegative = str_starts_with($normalized, '-');
        $normalized = ltrim($normalized, '+-');
        [$whole, $fraction] = array_pad(explode('.', $normalized, 2), 2, '0');
        $fraction = substr(str_pad($fraction, 2, '0'), 0, 2);

        return [
            'amount' => $whole.'.'.$fraction,
            'is_negative' => $isNegative,
        ];
    }

    private function detectType(bool $isNegative, ?string $amount, string $transferFlag): ?string
    {
        if ($amount === null || $amount === '0.00') {
            return 'unknown';
        }

        if ($this->isTransferFlagEnabled($transferFlag)) {
            return 'transfer';
        }

        return $isNegative ? 'expense' : 'income';
    }

    private function isTransferFlagEnabled(string $value): bool
    {
        $normalized = mb_strtolower(trim($value), 'UTF-8');

        return in_array($normalized, ['1', 'true', 'yes', 'y', '振替'], true);
    }

    private function buildDescription(
        string $institution,
        string $majorCategory,
        string $minorCategory,
        string $memo,
    ): ?string {
        $parts = array_values(array_filter([
            $this->nullableString($institution),
            $this->nullableString($majorCategory),
            $this->nullableString($minorCategory),
            $this->nullableString($memo),
        ]));

        if ($parts === []) {
            return null;
        }

        return implode(' / ', $parts);
    }

    private function nullableString(string $value): ?string
    {
        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function nullableCategoryString(string $value): ?string
    {
        $trimmed = $this->nullableString($value);

        if ($trimmed === null) {
            return null;
        }

        return $this->normalizeCategoryName($trimmed) === '未分類' ? null : $trimmed;
    }

    private function normalizeCategoryName(string $value): string
    {
        $normalized = mb_convert_kana($value, 'asKV', 'UTF-8');
        $normalized = preg_replace('/\s+/u', '', $normalized) ?? $normalized;

        return mb_strtolower($normalized, 'UTF-8');
    }

    private function parseCalculationTarget(string $value): ?bool
    {
        $normalized = trim($value);

        return match ($normalized) {
            '1' => true,
            '0' => false,
            '' => null,
            default => null,
        };
    }
}
