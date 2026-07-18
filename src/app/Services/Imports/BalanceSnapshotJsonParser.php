<?php

namespace App\Services\Imports;

use Carbon\CarbonImmutable;
use JsonException;
use RuntimeException;

class BalanceSnapshotJsonParser
{
    /**
     * @return array{rows: array<int, array<string, mixed>>, metadata: array<string, mixed>}
     */
    public function parse(string $contents): array
    {
        try {
            $payload = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new RuntimeException('残高取得JSONを読み取れませんでした。');
        }

        if (! is_array($payload)) {
            throw new RuntimeException('残高取得JSONの構造が不正です。');
        }

        if (($payload['format'] ?? null) !== 'nkkakeist-balance-snapshot' || ($payload['version'] ?? null) !== 1) {
            throw new RuntimeException('対応していない残高取得JSONです。');
        }

        $source = $this->requiredString($payload, 'source', '取得元');
        $capturedAt = $this->dateTime($payload['captured_at'] ?? null, '取得日時');
        $items = $payload['items'] ?? null;

        if (! is_array($items) || $items === [] || count($items) > 100) {
            throw new RuntimeException('残高取得JSONには1件以上100件以下の項目が必要です。');
        }

        $rows = [];

        foreach (array_values($items) as $index => $item) {
            if (! is_array($item)) {
                throw new RuntimeException(sprintf('残高取得JSONの%d件目が不正です。', $index + 1));
            }

            $rows[] = $this->parseItem($item, $index + 1, $source, $capturedAt);
        }

        return [
            'rows' => $rows,
            'metadata' => [
                'format' => 'nkkakeist-balance-snapshot',
                'version' => 1,
                'source' => $source,
                'captured_at' => $capturedAt->toIso8601String(),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private function parseItem(array $item, int $rowNumber, string $source, CarbonImmutable $capturedAt): array
    {
        $accountName = $this->requiredString($item, 'source_account_name', "{$rowNumber}件目の口座名");
        $balanceKind = $this->requiredString($item, 'balance_kind', "{$rowNumber}件目の残高種別");

        if (! in_array($balanceKind, ['valuation', 'account_balance', 'card_outstanding'], true)) {
            throw new RuntimeException(sprintf('%d件目の残高種別に対応していません。', $rowNumber));
        }

        $currency = strtoupper($this->requiredString($item, 'currency', "{$rowNumber}件目の通貨"));

        if (preg_match('/^[A-Z]{3}$/', $currency) !== 1) {
            throw new RuntimeException(sprintf('%d件目の通貨コードが不正です。', $rowNumber));
        }

        $sourceBalance = $this->amount($item['balance'] ?? null, "{$rowNumber}件目の残高");
        $balance = $balanceKind === 'card_outstanding'
            ? $this->negativeAbsoluteAmount($sourceBalance)
            : $sourceBalance;
        $sourceUpdatedAt = array_key_exists('source_updated_at', $item) && $item['source_updated_at'] !== null
            ? $this->dateTime($item['source_updated_at'], "{$rowNumber}件目の更新日時")
            : null;
        $balanceDate = $this->balanceDate($item['balance_date'] ?? null, $sourceUpdatedAt ?? $capturedAt, $rowNumber);
        $nextPaymentAmount = array_key_exists('next_payment_amount', $item) && $item['next_payment_amount'] !== null
            ? $this->amount($item['next_payment_amount'], "{$rowNumber}件目の次回引落額")
            : null;
        $nextPaymentDate = array_key_exists('next_payment_date', $item) && $item['next_payment_date'] !== null
            ? $this->date($item['next_payment_date'], "{$rowNumber}件目の次回引落日")
            : null;

        $rawPayload = [
            ...$item,
            'source' => $source,
            'captured_at' => $capturedAt->toIso8601String(),
            'balance' => $sourceBalance,
            'normalized_balance' => $balance,
            'currency' => $currency,
            'balance_date' => $balanceDate,
            'source_updated_at' => $sourceUpdatedAt?->toIso8601String(),
            'next_payment_amount' => $nextPaymentAmount,
            'next_payment_date' => $nextPaymentDate,
        ];

        return [
            'row_number' => $rowNumber,
            'raw_payload' => $rawPayload,
            'transaction_date' => $balanceDate,
            'amount' => $balance,
            'merchant_name' => $source,
            'description' => $balanceKind,
            'account_name' => $accountName,
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

    /**
     * @param  array<string, mixed>  $values
     */
    private function requiredString(array $values, string $key, string $label): string
    {
        $value = $values[$key] ?? null;

        if (! is_string($value) || trim($value) === '' || mb_strlen(trim($value)) > 255) {
            throw new RuntimeException("{$label}がありません。");
        }

        return trim($value);
    }

    private function amount(mixed $value, string $label): string
    {
        if (! is_int($value) && ! is_float($value) && ! is_string($value)) {
            throw new RuntimeException("{$label}が不正です。");
        }

        $normalized = trim((string) $value);

        if (preg_match('/^[+-]?\d{1,12}(?:\.\d{1,2})?$/', $normalized) !== 1) {
            throw new RuntimeException("{$label}が不正です。");
        }

        $isNegative = str_starts_with($normalized, '-');
        $normalized = ltrim($normalized, '+-');
        [$whole, $fraction] = array_pad(explode('.', $normalized, 2), 2, '0');
        $fraction = str_pad($fraction, 2, '0');

        return sprintf('%s%d.%s', $isNegative ? '-' : '', (int) $whole, $fraction);
    }

    private function negativeAbsoluteAmount(string $amount): string
    {
        return $amount === '0.00' ? '0.00' : '-'.ltrim($amount, '-');
    }

    private function dateTime(mixed $value, string $label): CarbonImmutable
    {
        if (! is_string($value) || trim($value) === '') {
            throw new RuntimeException("{$label}がありません。");
        }

        try {
            return CarbonImmutable::parse($value, config('app.timezone'));
        } catch (\Throwable) {
            throw new RuntimeException("{$label}を解釈できませんでした。");
        }
    }

    private function date(mixed $value, string $label): string
    {
        if (! is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            throw new RuntimeException("{$label}を解釈できませんでした。");
        }

        try {
            $date = CarbonImmutable::createFromFormat('!Y-m-d', $value, config('app.timezone'));
        } catch (\Throwable) {
            throw new RuntimeException("{$label}を解釈できませんでした。");
        }

        if ($date === false || $date->format('Y-m-d') !== $value) {
            throw new RuntimeException("{$label}を解釈できませんでした。");
        }

        return $value;
    }

    private function balanceDate(mixed $value, CarbonImmutable $fallback, int $rowNumber): string
    {
        if ($value === null) {
            return $fallback->toDateString();
        }

        return $this->date($value, "{$rowNumber}件目の残高日");
    }
}
