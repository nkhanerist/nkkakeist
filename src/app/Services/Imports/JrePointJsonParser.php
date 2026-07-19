<?php

namespace App\Services\Imports;

use DateTimeImmutable;
use JsonException;
use RuntimeException;

class JrePointJsonParser
{
    /**
     * @return array{
     *     rows: array<int, array<string, mixed>>,
     *     metadata: array<string, mixed>
     * }
     */
    public function parse(string $contents): array
    {
        try {
            $payload = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new RuntimeException(trans('imports.parse_errors.jre_point_json_unreadable'));
        }

        if (! is_array($payload)) {
            throw new RuntimeException(trans('imports.parse_errors.jre_point_json_unrecognized'));
        }

        if (($payload['format'] ?? null) !== 'nkkakeist-jre-point-history' || ($payload['version'] ?? null) !== 1) {
            throw new RuntimeException(trans('imports.parse_errors.jre_point_version_unsupported'));
        }

        $capturedAt = $this->capturedAt($payload['captured_at'] ?? null);
        $balance = $this->balance($payload['balance'] ?? null);
        $sourceRows = $payload['rows'] ?? null;

        if (! is_array($sourceRows)) {
            throw new RuntimeException(trans('imports.parse_errors.jre_point_rows_missing'));
        }

        $rows = [];
        $signatureOccurrences = [];

        foreach ($sourceRows as $index => $sourceRow) {
            if (! is_array($sourceRow)) {
                throw new RuntimeException(trans('imports.parse_errors.jre_point_row_invalid', [
                    'row' => $index + 1,
                ]));
            }

            $reflectionDate = $this->date(
                $sourceRow['reflection_date'] ?? null,
                trans('imports.parse_fields.jre_reflection_date'),
                $index,
            );
            $place = $this->requiredString(
                $sourceRow['place'] ?? null,
                trans('imports.parse_fields.jre_place'),
                $index,
            );
            $content = $this->requiredString(
                $sourceRow['description'] ?? null,
                trans('imports.parse_fields.jre_description'),
                $index,
            );
            $points = $this->points($sourceRow['points'] ?? null, $index);

            if ($points === 0) {
                continue;
            }

            $sourceIcon = $this->nullableString($sourceRow['source_icon'] ?? null);
            $signature = implode('|', [$reflectionDate, $place, $content, $points, $sourceIcon ?? '']);
            $occurrence = ($signatureOccurrences[$signature] ?? 0) + 1;
            $signatureOccurrences[$signature] = $occurrence;
            $externalId = 'jre-point-v1-'.substr(hash('sha256', $signature.'|'.$occurrence), 0, 24);
            $isCharge = $points < 0 && $this->isMobileSuicaCharge($place, $content);
            $transactionDate = $isCharge
                ? $this->chargeDate($sourceRow['actual_date'] ?? null, $reflectionDate, $content)
                : $reflectionDate;
            $detectedType = $isCharge ? 'transfer' : ($points > 0 ? 'income' : 'expense');

            $rows[] = [
                'row_number' => $index + 1,
                'raw_payload' => [
                    'ID' => $externalId,
                    'ポイント反映日' => $reflectionDate,
                    '利用場所' => $place,
                    '内容' => $content,
                    'ポイント' => (string) $points,
                    'アイコン' => $sourceIcon ?? '',
                    '金額' => (string) $points,
                    'メモ' => sprintf('ポイント反映日:%s / %s', $reflectionDate, $content),
                ],
                'transaction_date' => $transactionDate,
                'amount' => number_format(abs($points), 2, '.', ''),
                'account_name' => 'JREポイント',
                'category_name' => $detectedType === 'income' ? '収入' : ($detectedType === 'expense' ? 'ポイント利用' : null),
                'subcategory_name' => $detectedType === 'income' ? 'ポイント獲得' : null,
                'merchant_name' => $isCharge ? 'モバイルSuica チャージ' : $place,
                'description' => sprintf('JRE POINT / %s / %s', $place, $content),
                'detected_type' => $detectedType,
                'is_calculation_target' => false,
                'affects_account_balance' => true,
            ];
        }

        return [
            'rows' => $rows,
            'metadata' => [
                'format' => 'nkkakeist-jre-point-history',
                'version' => 1,
                'captured_at' => $capturedAt,
                'balance' => $balance,
                'page_count' => is_numeric($payload['page_count'] ?? null)
                    ? (int) $payload['page_count']
                    : null,
            ],
        ];
    }

    private function capturedAt(mixed $value): string
    {
        if (! is_string($value) || trim($value) === '') {
            throw new RuntimeException(trans('imports.parse_errors.jre_point_captured_at_missing'));
        }

        try {
            return (new DateTimeImmutable($value))->format(DATE_ATOM);
        } catch (\Throwable) {
            throw new RuntimeException(trans('imports.parse_errors.jre_point_captured_at_invalid'));
        }
    }

    /** @return array{total:int, limited:int, regular:int, nearest_expiry:string|null} */
    private function balance(mixed $value): array
    {
        if (! is_array($value) || ! is_numeric($value['total'] ?? null)) {
            throw new RuntimeException(trans('imports.parse_errors.jre_point_total_unreadable'));
        }

        $total = (int) $value['total'];
        $limited = is_numeric($value['limited'] ?? null) ? (int) $value['limited'] : 0;

        if ($total < 0 || $limited < 0 || $limited > $total) {
            throw new RuntimeException(trans('imports.parse_errors.jre_point_balance_invalid'));
        }

        $nearestExpiry = $this->nullableString($value['nearest_expiry'] ?? null);

        if ($nearestExpiry !== null && ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $nearestExpiry)) {
            throw new RuntimeException(trans('imports.parse_errors.jre_point_expiry_invalid'));
        }

        return [
            'total' => $total,
            'limited' => $limited,
            'regular' => $total - $limited,
            'nearest_expiry' => $nearestExpiry,
        ];
    }

    private function date(mixed $value, string $label, int $index): string
    {
        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            throw new RuntimeException(trans('imports.parse_errors.jre_point_field_invalid', [
                'row' => $index + 1,
                'field' => $label,
            ]));
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        $errors = DateTimeImmutable::getLastErrors();

        if (! $date instanceof DateTimeImmutable || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
            throw new RuntimeException(trans('imports.parse_errors.jre_point_field_invalid', [
                'row' => $index + 1,
                'field' => $label,
            ]));
        }

        return $date->format('Y-m-d');
    }

    private function requiredString(mixed $value, string $label, int $index): string
    {
        $normalized = $this->nullableString($value);

        if ($normalized === null) {
            throw new RuntimeException(trans('imports.parse_errors.jre_point_field_missing', [
                'row' => $index + 1,
                'field' => $label,
            ]));
        }

        return $normalized;
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $normalized = trim($value);

        return $normalized === '' ? null : $normalized;
    }

    private function points(mixed $value, int $index): int
    {
        if (! is_int($value) && ! (is_string($value) && preg_match('/^[+-]?\d+$/', trim($value)))) {
            throw new RuntimeException(trans('imports.parse_errors.jre_point_points_invalid', [
                'row' => $index + 1,
            ]));
        }

        return (int) $value;
    }

    private function isMobileSuicaCharge(string $place, string $content): bool
    {
        $normalizedPlace = mb_convert_kana($place, 'asKV', 'UTF-8');
        $normalizedContent = mb_convert_kana($content, 'asKV', 'UTF-8');

        return str_contains($normalizedPlace, 'モバイルSuica')
            && str_contains($normalizedContent, 'チャージ');
    }

    private function chargeDate(mixed $actualDate, string $reflectionDate, string $content): string
    {
        if (is_string($actualDate) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $actualDate)) {
            return $this->date(
                $actualDate,
                trans('imports.parse_fields.jre_actual_date'),
                0,
            );
        }

        $normalizedContent = mb_convert_kana($content, 'as', 'UTF-8');

        if (! preg_match('/(?<!\d)(\d{1,2})\/(\d{1,2})(?!\d)/', $normalizedContent, $matches)) {
            return $reflectionDate;
        }

        $reflection = new DateTimeImmutable($reflectionDate);
        $year = (int) $reflection->format('Y');
        $month = (int) $matches[1];

        if ($month > (int) $reflection->format('n')) {
            $year--;
        }

        $candidate = DateTimeImmutable::createFromFormat('!Y-n-j', sprintf('%d-%d-%d', $year, $month, (int) $matches[2]));

        return $candidate instanceof DateTimeImmutable ? $candidate->format('Y-m-d') : $reflectionDate;
    }
}
