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
        $assetHistory = $this->assetHistory($payload['asset_history'] ?? null, $capturedAt);

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
                'asset_history' => $assetHistory,
            ],
        ];
    }

    /** @return array{captured_on: string, total_assets: string, currency: string, breakdown: array<string, string>}|null */
    private function assetHistory(mixed $value, CarbonImmutable $capturedAt): ?array
    {
        if ($value === null) {
            return null;
        }

        if (! is_array($value)) {
            throw new RuntimeException('総資産サマリーが不正です。');
        }

        $currency = strtoupper($this->requiredString($value, 'currency', '総資産サマリーの通貨'));

        if (preg_match('/^[A-Z]{3}$/', $currency) !== 1) {
            throw new RuntimeException('総資産サマリーの通貨コードが不正です。');
        }

        $breakdownValue = $value['breakdown'] ?? [];

        if (! is_array($breakdownValue) || count($breakdownValue) > 30) {
            throw new RuntimeException('総資産サマリーの内訳が不正です。');
        }

        $breakdown = [];

        foreach ($breakdownValue as $label => $amount) {
            if (! is_string($label) || trim($label) === '' || mb_strlen(trim($label)) > 64) {
                throw new RuntimeException('総資産サマリーの内訳名が不正です。');
            }

            $breakdown[trim($label)] = $this->amount($amount, "総資産サマリーの{$label}");
        }

        return [
            'captured_on' => $this->balanceDate($value['captured_on'] ?? null, $capturedAt, 0),
            'total_assets' => $this->amount($value['total_assets'] ?? null, '総資産サマリーの合計'),
            'currency' => $currency,
            'breakdown' => $breakdown,
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
        $positions = $this->positions($item['positions'] ?? null, $rowNumber, $currency);

        if ($positions !== [] && $balanceKind !== 'valuation') {
            throw new RuntimeException(sprintf('%d件目の銘柄明細は時価評価額にのみ指定できます。', $rowNumber));
        }

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
            'positions' => $positions,
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
     * @return array<int, array<string, mixed>>
     */
    private function positions(mixed $value, int $rowNumber, string $accountCurrency): array
    {
        if ($value === null) {
            return [];
        }

        if (! is_array($value) || count($value) > 200) {
            throw new RuntimeException(sprintf('%d件目の銘柄明細は200件以下で指定してください。', $rowNumber));
        }

        $positions = [];
        $seenIdentities = [];

        foreach (array_values($value) as $positionIndex => $position) {
            $label = sprintf('%d件目の銘柄明細%d件目', $rowNumber, $positionIndex + 1);

            if (! is_array($position)) {
                throw new RuntimeException("{$label}が不正です。");
            }

            $currency = array_key_exists('currency', $position)
                ? strtoupper($this->requiredString($position, 'currency', "{$label}の通貨"))
                : $accountCurrency;

            if (preg_match('/^[A-Z]{3}$/', $currency) !== 1) {
                throw new RuntimeException("{$label}の通貨コードが不正です。");
            }

            $normalizedPosition = [
                'instrument_name' => $this->requiredString($position, 'instrument_name', "{$label}の銘柄名"),
                'instrument_code' => $this->optionalString($position['instrument_code'] ?? null, "{$label}の銘柄コード", 64),
                'external_id' => $this->optionalString($position['external_id'] ?? null, "{$label}の外部識別子"),
                'asset_class' => $this->optionalString($position['asset_class'] ?? null, "{$label}の資産区分", 64),
                'quantity' => $this->optionalDecimal($position['quantity'] ?? null, "{$label}の保有数量", 8),
                'average_acquisition_price' => $this->optionalDecimal(
                    $position['average_acquisition_price'] ?? null,
                    "{$label}の平均取得単価",
                    6,
                ),
                'unit_price' => $this->optionalDecimal($position['unit_price'] ?? null, "{$label}の現在値", 6),
                'acquisition_cost' => array_key_exists('acquisition_cost', $position)
                    && $position['acquisition_cost'] !== null
                        ? $this->amount($position['acquisition_cost'], "{$label}の取得価額")
                        : null,
                'valuation' => $this->amount($position['valuation'] ?? null, "{$label}の評価額"),
                'unrealized_gain' => array_key_exists('unrealized_gain', $position)
                    && $position['unrealized_gain'] !== null
                        ? $this->amount($position['unrealized_gain'], "{$label}の評価損益")
                        : null,
                'currency' => $currency,
            ];
            $identity = $normalizedPosition['external_id']
                ?? $normalizedPosition['instrument_code']
                ?? $normalizedPosition['instrument_name'];
            $identityKey = mb_strtolower($identity.'|'.$currency, 'UTF-8');

            if (isset($seenIdentities[$identityKey])) {
                throw new RuntimeException("{$label}が同じ口座内で重複しています。");
            }

            $seenIdentities[$identityKey] = true;
            $positions[] = $normalizedPosition;
        }

        return $positions;
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

    private function optionalString(mixed $value, string $label, int $maxLength = 255): ?string
    {
        if ($value === null) {
            return null;
        }

        if (! is_string($value) || mb_strlen(trim($value)) > $maxLength) {
            throw new RuntimeException("{$label}が不正です。");
        }

        $normalized = trim($value);

        return $normalized === '' ? null : $normalized;
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

    private function optionalDecimal(mixed $value, string $label, int $scale): ?string
    {
        if ($value === null) {
            return null;
        }

        if (! is_int($value) && ! is_float($value) && ! is_string($value)) {
            throw new RuntimeException("{$label}が不正です。");
        }

        $normalized = trim((string) $value);

        if (preg_match('/^[+-]?\d{1,16}(?:\.\d{1,'.$scale.'})?$/', $normalized) !== 1) {
            throw new RuntimeException("{$label}が不正です。");
        }

        $isNegative = str_starts_with($normalized, '-');
        $normalized = ltrim($normalized, '+-');
        [$whole, $fraction] = array_pad(explode('.', $normalized, 2), 2, '');
        $fraction = str_pad($fraction, $scale, '0');

        return sprintf('%s%d.%s', $isNegative ? '-' : '', (int) $whole, $fraction);
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
