<?php

namespace App\Services\Imports;

use DateTimeImmutable;
use RuntimeException;

class MobileSuicaPdfParser
{
    public function __construct(
        private readonly PdfTextExtractorService $pdfTextExtractorService,
    ) {}

    /**
     * @return array<int, array{
     *     row_number: int,
     *     raw_payload: array<string, string>,
     *     transaction_date: string,
     *     amount: string,
     *     account_name: string,
     *     category_name: string|null,
     *     subcategory_name: string|null,
     *     merchant_name: string,
     *     description: string,
     *     detected_type: string,
     *     is_calculation_target: bool
     * }>
     */
    public function parse(string $contents): array
    {
        return $this->parseExtractedText($this->pdfTextExtractorService->extract($contents));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function parseExtractedText(string $text): array
    {
        $suicaIdSuffix = $this->suicaIdSuffix($text);
        $issuedAt = $this->issuedAt($text);
        $parsedRows = [];
        $seenExternalIds = [];
        $previousBalance = null;
        $recognizedHistoryRows = 0;

        foreach (preg_split('/\R/u', $text) ?: [] as $lineIndex => $line) {
            $carryBalance = $this->carryBalance($line);

            if ($carryBalance !== null) {
                $previousBalance = $carryBalance;

                continue;
            }

            $history = $this->historyRow($line, $issuedAt);

            if ($history === null) {
                continue;
            }

            $recognizedHistoryRows++;

            if (
                $previousBalance !== null
                && $previousBalance + $history['signed_amount'] !== $history['balance']
            ) {
                throw new RuntimeException(trans('imports.parse_errors.mobile_suica_balance_discontinuous', [
                    'date' => $history['transaction_date'],
                    'row' => $lineIndex + 1,
                ]));
            }

            $previousBalance = $history['balance'];

            if ($history['signed_amount'] >= 0) {
                continue;
            }

            [$merchantName, $categoryName, $subcategoryName] = $this->classification($history);
            $externalId = $this->externalId($suicaIdSuffix, $history);

            if (isset($seenExternalIds[$externalId])) {
                continue;
            }

            $seenExternalIds[$externalId] = true;
            $description = implode(' / ', array_filter([
                'モバイルSuica',
                $categoryName,
                $subcategoryName,
                'Suica種別:'.$history['kind'],
                '利用後残高:'.$history['balance'].'円',
            ]));

            $parsedRows[] = [
                'row_number' => $lineIndex + 1,
                'raw_payload' => [
                    'ID' => $externalId,
                    'Suica ID末尾' => $suicaIdSuffix,
                    '日付' => $history['transaction_date'],
                    '種別1' => $history['kind'],
                    '利用駅1' => $history['origin'],
                    '種別2' => $history['destination'] === '' ? '' : '出',
                    '利用駅2' => $history['destination'],
                    '残高' => (string) $history['balance'],
                    '入金・利用額' => (string) $history['signed_amount'],
                    'メモ' => 'Suica種別:'.$history['kind'].' / 利用後残高:'.$history['balance'].'円',
                ],
                'transaction_date' => $history['transaction_date'],
                'amount' => number_format(abs($history['signed_amount']), 2, '.', ''),
                'account_name' => 'モバイルSuica',
                'category_name' => $categoryName,
                'subcategory_name' => $subcategoryName,
                'merchant_name' => $merchantName,
                'description' => $description,
                'detected_type' => 'expense',
                'is_calculation_target' => true,
                'affects_account_balance' => true,
            ];
        }

        if ($recognizedHistoryRows === 0) {
            throw new RuntimeException(trans('imports.parse_errors.mobile_suica_history_unreadable'));
        }

        if ($parsedRows === []) {
            throw new RuntimeException(trans('imports.parse_errors.mobile_suica_no_expenses'));
        }

        return $parsedRows;
    }

    private function suicaIdSuffix(string $text): string
    {
        if (! preg_match('/JE\*{3}\s+\*{4}\s+\*{4}\s+(\d{4})/u', $text, $matches)) {
            throw new RuntimeException(trans('imports.parse_errors.mobile_suica_unrecognized'));
        }

        return $matches[1];
    }

    private function issuedAt(string $text): DateTimeImmutable
    {
        if (! preg_match('/(?<!\d)(20\d{2})\/(\d{1,2})\/(\d{1,2})(?!\d)/u', $text, $matches)) {
            throw new RuntimeException(trans('imports.parse_errors.mobile_suica_issued_at_missing'));
        }

        $date = DateTimeImmutable::createFromFormat('!Y-n-j', implode('-', [
            $matches[1],
            $matches[2],
            $matches[3],
        ]));
        $dateErrors = DateTimeImmutable::getLastErrors();

        if (
            ! $date instanceof DateTimeImmutable
            || ($dateErrors !== false && ($dateErrors['warning_count'] > 0 || $dateErrors['error_count'] > 0))
        ) {
            throw new RuntimeException(trans('imports.parse_errors.mobile_suica_issued_at_invalid'));
        }

        return $date;
    }

    private function carryBalance(string $line): ?int
    {
        if (! preg_match('/^\s*\d{2}\s+\d{2}\s+繰\s+[^\d\s]([\d,]+)\s*$/u', $line, $matches)) {
            return null;
        }

        return (int) str_replace(',', '', $matches[1]);
    }

    /**
     * @return array{
     *     transaction_date:string,
     *     kind:string,
     *     origin:string,
     *     destination:string,
     *     balance:int,
     *     signed_amount:int
     * }|null
     */
    private function historyRow(string $line, DateTimeImmutable $issuedAt): ?array
    {
        if (! preg_match(
            '/^\s*(\d{2})\s+(\d{2})\s+(\S+)(.*?)\s+[^\d\s]([\d,]+)\s+([+-]?[\d,]+)\s*$/u',
            $line,
            $matches,
        )) {
            return null;
        }

        $month = (int) $matches[1];
        $day = (int) $matches[2];
        $kind = mb_convert_kana($matches[3], 'KV', 'UTF-8');
        $middle = trim($matches[4]);
        $locations = preg_split('/\s+出\s+/u', $middle, 2) ?: [];
        $origin = $this->normalizeLocation($locations[0] ?? '');
        $destination = $this->normalizeLocation($locations[1] ?? '');
        $transactionDate = $this->historyDate($issuedAt, $month, $day);

        return [
            'transaction_date' => $transactionDate->format('Y-m-d'),
            'kind' => $kind,
            'origin' => $origin,
            'destination' => $destination,
            'balance' => (int) str_replace(',', '', $matches[5]),
            'signed_amount' => (int) str_replace(',', '', $matches[6]),
        ];
    }

    private function historyDate(DateTimeImmutable $issuedAt, int $month, int $day): DateTimeImmutable
    {
        $year = (int) $issuedAt->format('Y');
        $date = DateTimeImmutable::createFromFormat('!Y-n-j', "$year-$month-$day");
        $dateErrors = DateTimeImmutable::getLastErrors();

        if (
            ! $date instanceof DateTimeImmutable
            || ($dateErrors !== false && ($dateErrors['warning_count'] > 0 || $dateErrors['error_count'] > 0))
        ) {
            throw new RuntimeException(trans('imports.parse_errors.mobile_suica_transaction_date_invalid'));
        }

        if ($date > $issuedAt) {
            $date = $date->modify('-1 year');
        }

        return $date;
    }

    private function normalizeLocation(string $value): string
    {
        return preg_replace('/\s+/u', '', trim($value)) ?? trim($value);
    }

    /**
     * @param  array{kind:string, origin:string, destination:string}  $history
     * @return array{0:string, 1:string|null, 2:string|null}
     */
    private function classification(array $history): array
    {
        if ($history['kind'] === '物販') {
            return ['モバイルSuica 物販', null, null];
        }

        if (str_contains($history['kind'], 'バス')) {
            $merchantName = trim('モバイルSuica バス '.$history['origin']);

            return [$merchantName, '交通費', '交通費'];
        }

        $route = $history['origin'];

        if ($history['destination'] !== '') {
            $route .= ' → '.$history['destination'];
        }

        return [trim('モバイルSuica '.$route), '交通費', '電車'];
    }

    /**
     * @param  array{
     *     transaction_date:string,
     *     kind:string,
     *     origin:string,
     *     destination:string,
     *     balance:int,
     *     signed_amount:int
     * }  $history
     */
    private function externalId(string $suicaIdSuffix, array $history): string
    {
        $signature = implode('|', [
            $history['transaction_date'],
            $history['kind'],
            $history['origin'],
            $history['destination'],
            $history['balance'],
            $history['signed_amount'],
        ]);

        return 'mobile-suica-'.$suicaIdSuffix.'-'.substr(hash('sha256', $signature), 0, 24);
    }
}
