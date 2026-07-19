<?php

namespace App\Services\Imports;

use Carbon\CarbonImmutable;
use JsonException;
use RuntimeException;

class BalanceSnapshotJsonParser
{
    public function __construct(
        private readonly InvestmentPositionIdentityService $positionIdentityService,
    ) {}

    /**
     * @return array{rows: array<int, array<string, mixed>>, metadata: array<string, mixed>}
     */
    public function parse(string $contents): array
    {
        try {
            $payload = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new RuntimeException(trans('imports.parse_errors.balance_json_unreadable'));
        }

        if (! is_array($payload)) {
            throw new RuntimeException(trans('imports.parse_errors.balance_json_structure_invalid'));
        }

        if (($payload['format'] ?? null) !== 'nkkakeist-balance-snapshot' || ($payload['version'] ?? null) !== 1) {
            throw new RuntimeException(trans('imports.parse_errors.balance_json_unsupported'));
        }

        $source = $this->requiredString(
            $payload,
            'source',
            trans('imports.parse_fields.balance_source'),
        );
        $capturedAt = $this->dateTime(
            $payload['captured_at'] ?? null,
            trans('imports.parse_fields.balance_captured_at'),
        );
        $diagnostics = $this->diagnostics($payload['diagnostics'] ?? null);
        $items = $payload['items'] ?? null;
        $assetHistory = $this->assetHistory($payload['asset_history'] ?? null, $capturedAt);

        if (! is_array($items) || $items === [] || count($items) > 100) {
            throw new RuntimeException(trans('imports.parse_errors.balance_items_invalid'));
        }

        $rows = [];

        foreach (array_values($items) as $index => $item) {
            if (! is_array($item)) {
                throw new RuntimeException(trans('imports.parse_errors.balance_item_invalid', [
                    'row' => $index + 1,
                ]));
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
                'acquisition_diagnostics' => $diagnostics,
                'asset_history' => $assetHistory,
            ],
        ];
    }

    /**
     * @return array{exporter_version: int, portfolio_summary_table: bool, investment_tables: int, deposit_table: bool, pension_table: bool, liability_tables: int}|null
     */
    private function diagnostics(mixed $value): ?array
    {
        if ($value === null) {
            return null;
        }

        if (! is_array($value)) {
            throw new RuntimeException(trans('imports.parse_errors.balance_diagnostics_invalid'));
        }

        $exporterVersion = $value['exporter_version'] ?? null;
        $portfolioSummaryTable = $value['portfolio_summary_table'] ?? null;
        $investmentTables = $value['investment_tables'] ?? null;
        $depositTable = $value['deposit_table'] ?? null;
        $pensionTable = $value['pension_table'] ?? null;
        $liabilityTables = $value['liability_tables'] ?? null;

        if (
            ! is_int($exporterVersion)
            || $exporterVersion < 2
            || ! is_bool($portfolioSummaryTable)
            || ! is_int($investmentTables)
            || $investmentTables < 0
            || $investmentTables > 100
            || ! is_bool($depositTable)
            || ! is_bool($pensionTable)
            || ! is_int($liabilityTables)
            || $liabilityTables < 0
            || $liabilityTables > 100
        ) {
            throw new RuntimeException(trans('imports.parse_errors.balance_diagnostics_invalid'));
        }

        return [
            'exporter_version' => $exporterVersion,
            'portfolio_summary_table' => $portfolioSummaryTable,
            'investment_tables' => $investmentTables,
            'deposit_table' => $depositTable,
            'pension_table' => $pensionTable,
            'liability_tables' => $liabilityTables,
        ];
    }

    /** @return array{captured_on: string, total_assets: string, currency: string, breakdown: array<string, string>}|null */
    private function assetHistory(mixed $value, CarbonImmutable $capturedAt): ?array
    {
        if ($value === null) {
            return null;
        }

        if (! is_array($value)) {
            throw new RuntimeException(trans('imports.parse_errors.balance_summary_invalid'));
        }

        $currency = strtoupper($this->requiredString(
            $value,
            'currency',
            trans('imports.parse_fields.balance_summary_currency'),
        ));

        if (preg_match('/^[A-Z]{3}$/', $currency) !== 1) {
            throw new RuntimeException(trans('imports.parse_errors.balance_summary_currency_invalid'));
        }

        $breakdownValue = $value['breakdown'] ?? [];

        if (! is_array($breakdownValue) || count($breakdownValue) > 30) {
            throw new RuntimeException(trans('imports.parse_errors.balance_summary_breakdown_invalid'));
        }

        $breakdown = [];

        foreach ($breakdownValue as $label => $amount) {
            if (! is_string($label) || trim($label) === '' || mb_strlen(trim($label)) > 64) {
                throw new RuntimeException(trans('imports.parse_errors.balance_summary_breakdown_label_invalid'));
            }

            $breakdown[trim($label)] = $this->amount(
                $amount,
                trans('imports.parse_fields.balance_summary_amount', ['label' => $label]),
            );
        }

        return [
            'captured_on' => $this->balanceDate($value['captured_on'] ?? null, $capturedAt, 0),
            'total_assets' => $this->amount(
                $value['total_assets'] ?? null,
                trans('imports.parse_fields.balance_summary_total'),
            ),
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
        $accountName = $this->requiredString(
            $item,
            'source_account_name',
            trans('imports.parse_fields.balance_item_account', ['row' => $rowNumber]),
        );
        $balanceKind = $this->requiredString(
            $item,
            'balance_kind',
            trans('imports.parse_fields.balance_item_kind', ['row' => $rowNumber]),
        );

        if (! in_array($balanceKind, ['valuation', 'account_balance', 'card_outstanding'], true)) {
            throw new RuntimeException(trans('imports.parse_errors.balance_kind_unsupported', [
                'row' => $rowNumber,
            ]));
        }

        $currency = strtoupper($this->requiredString(
            $item,
            'currency',
            trans('imports.parse_fields.balance_item_currency', ['row' => $rowNumber]),
        ));

        if (preg_match('/^[A-Z]{3}$/', $currency) !== 1) {
            throw new RuntimeException(trans('imports.parse_errors.balance_currency_invalid', [
                'row' => $rowNumber,
            ]));
        }

        $sourceBalance = $this->amount(
            $item['balance'] ?? null,
            trans('imports.parse_fields.balance_item_balance', ['row' => $rowNumber]),
        );
        $balance = $balanceKind === 'card_outstanding'
            ? $this->negativeAbsoluteAmount($sourceBalance)
            : $sourceBalance;
        $sourceUpdatedAt = array_key_exists('source_updated_at', $item) && $item['source_updated_at'] !== null
            ? $this->dateTime(
                $item['source_updated_at'],
                trans('imports.parse_fields.balance_item_updated_at', ['row' => $rowNumber]),
            )
            : null;
        $balanceDate = $this->balanceDate($item['balance_date'] ?? null, $sourceUpdatedAt ?? $capturedAt, $rowNumber);
        $nextPaymentAmount = array_key_exists('next_payment_amount', $item) && $item['next_payment_amount'] !== null
            ? $this->amount(
                $item['next_payment_amount'],
                trans('imports.parse_fields.balance_item_next_payment_amount', ['row' => $rowNumber]),
            )
            : null;
        $nextPaymentDate = array_key_exists('next_payment_date', $item) && $item['next_payment_date'] !== null
            ? $this->date(
                $item['next_payment_date'],
                trans('imports.parse_fields.balance_item_next_payment_date', ['row' => $rowNumber]),
            )
            : null;
        $positions = $this->positions(
            $item['positions'] ?? null,
            $rowNumber,
            $currency,
            $source,
            $accountName,
        );

        if ($positions !== [] && $balanceKind !== 'valuation') {
            throw new RuntimeException(trans('imports.parse_errors.balance_positions_only_valuation', [
                'row' => $rowNumber,
            ]));
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
    private function positions(
        mixed $value,
        int $rowNumber,
        string $accountCurrency,
        string $source,
        string $sourceAccountName,
    ): array {
        if ($value === null) {
            return [];
        }

        if (! is_array($value) || count($value) > 200) {
            throw new RuntimeException(trans('imports.parse_errors.balance_positions_limit', [
                'row' => $rowNumber,
            ]));
        }

        $positions = [];
        $seenPositionKeys = [];
        $seenSemanticKeys = [];

        foreach (array_values($value) as $positionIndex => $position) {
            $label = trans('imports.parse_fields.balance_position', [
                'row' => $rowNumber,
                'position' => $positionIndex + 1,
            ]);

            if (! is_array($position)) {
                throw new RuntimeException(trans('imports.parse_errors.balance_position_invalid', [
                    'position' => $label,
                ]));
            }

            $currency = array_key_exists('currency', $position)
                ? strtoupper($this->requiredString(
                    $position,
                    'currency',
                    $this->positionFieldLabel($label, 'currency'),
                ))
                : $accountCurrency;

            if (preg_match('/^[A-Z]{3}$/', $currency) !== 1) {
                throw new RuntimeException(trans('imports.parse_errors.balance_position_currency_invalid', [
                    'position' => $label,
                ]));
            }

            $normalizedPosition = [
                'instrument_name' => $this->requiredString(
                    $position,
                    'instrument_name',
                    $this->positionFieldLabel($label, 'instrument_name'),
                ),
                'instrument_code' => $this->optionalString(
                    $position['instrument_code'] ?? null,
                    $this->positionFieldLabel($label, 'instrument_code'),
                    64,
                ),
                'external_id' => $this->optionalString(
                    $position['external_id'] ?? null,
                    $this->positionFieldLabel($label, 'external_id'),
                ),
                'asset_class' => $this->optionalString(
                    $position['asset_class'] ?? null,
                    $this->positionFieldLabel($label, 'asset_class'),
                    64,
                ),
                'quantity' => $this->optionalDecimal(
                    $position['quantity'] ?? null,
                    $this->positionFieldLabel($label, 'quantity'),
                    8,
                ),
                'average_acquisition_price' => $this->optionalDecimal(
                    $position['average_acquisition_price'] ?? null,
                    $this->positionFieldLabel($label, 'average_acquisition_price'),
                    6,
                ),
                'unit_price' => $this->optionalDecimal(
                    $position['unit_price'] ?? null,
                    $this->positionFieldLabel($label, 'unit_price'),
                    6,
                ),
                'acquisition_cost' => array_key_exists('acquisition_cost', $position)
                    && $position['acquisition_cost'] !== null
                        ? $this->amount(
                            $position['acquisition_cost'],
                            $this->positionFieldLabel($label, 'acquisition_cost'),
                        )
                        : null,
                'valuation' => $this->amount(
                    $position['valuation'] ?? null,
                    $this->positionFieldLabel($label, 'valuation'),
                ),
                'unrealized_gain' => array_key_exists('unrealized_gain', $position)
                    && $position['unrealized_gain'] !== null
                        ? $this->amount(
                            $position['unrealized_gain'],
                            $this->positionFieldLabel($label, 'unrealized_gain'),
                        )
                        : null,
                'currency' => $currency,
            ];
            $positionKey = $this->positionIdentityService->positionKey(
                $normalizedPosition,
                $source,
                $sourceAccountName,
            );
            $semanticKey = $this->positionIdentityService->semanticKey(
                $normalizedPosition['instrument_name'],
                $currency,
            );

            if (isset($seenPositionKeys[$positionKey]) || isset($seenSemanticKeys[$semanticKey])) {
                throw new RuntimeException(trans('imports.parse_errors.balance_position_duplicate', [
                    'position' => $label,
                ]));
            }

            $seenPositionKeys[$positionKey] = true;
            $seenSemanticKeys[$semanticKey] = true;
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
            throw new RuntimeException(trans('imports.parse_errors.field_missing', [
                'field' => $label,
            ]));
        }

        return trim($value);
    }

    private function optionalString(mixed $value, string $label, int $maxLength = 255): ?string
    {
        if ($value === null) {
            return null;
        }

        if (! is_string($value) || mb_strlen(trim($value)) > $maxLength) {
            throw new RuntimeException(trans('imports.parse_errors.field_invalid', [
                'field' => $label,
            ]));
        }

        $normalized = trim($value);

        return $normalized === '' ? null : $normalized;
    }

    private function amount(mixed $value, string $label): string
    {
        if (! is_int($value) && ! is_float($value) && ! is_string($value)) {
            throw new RuntimeException(trans('imports.parse_errors.field_invalid', [
                'field' => $label,
            ]));
        }

        $normalized = trim((string) $value);

        if (preg_match('/^[+-]?\d{1,12}(?:\.\d{1,2})?$/', $normalized) !== 1) {
            throw new RuntimeException(trans('imports.parse_errors.field_invalid', [
                'field' => $label,
            ]));
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
            throw new RuntimeException(trans('imports.parse_errors.field_invalid', [
                'field' => $label,
            ]));
        }

        $normalized = trim((string) $value);

        if (preg_match('/^[+-]?\d{1,16}(?:\.\d{1,'.$scale.'})?$/', $normalized) !== 1) {
            throw new RuntimeException(trans('imports.parse_errors.field_invalid', [
                'field' => $label,
            ]));
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
            throw new RuntimeException(trans('imports.parse_errors.field_missing', [
                'field' => $label,
            ]));
        }

        try {
            return CarbonImmutable::parse($value, config('app.timezone'));
        } catch (\Throwable) {
            throw new RuntimeException(trans('imports.parse_errors.field_unparseable', [
                'field' => $label,
            ]));
        }
    }

    private function date(mixed $value, string $label): string
    {
        if (! is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            throw new RuntimeException(trans('imports.parse_errors.field_unparseable', [
                'field' => $label,
            ]));
        }

        try {
            $date = CarbonImmutable::createFromFormat('!Y-m-d', $value, config('app.timezone'));
        } catch (\Throwable) {
            throw new RuntimeException(trans('imports.parse_errors.field_unparseable', [
                'field' => $label,
            ]));
        }

        if ($date === false || $date->format('Y-m-d') !== $value) {
            throw new RuntimeException(trans('imports.parse_errors.field_unparseable', [
                'field' => $label,
            ]));
        }

        return $value;
    }

    private function balanceDate(mixed $value, CarbonImmutable $fallback, int $rowNumber): string
    {
        if ($value === null) {
            return $fallback->toDateString();
        }

        return $this->date(
            $value,
            trans('imports.parse_fields.balance_item_date', ['row' => $rowNumber]),
        );
    }

    private function positionFieldLabel(string $position, string $field): string
    {
        return trans('imports.parse_fields.balance_position_field', [
            'position' => $position,
            'field' => trans("imports.parse_fields.{$field}"),
        ]);
    }
}
