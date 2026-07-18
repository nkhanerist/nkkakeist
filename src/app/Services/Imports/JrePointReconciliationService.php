<?php

namespace App\Services\Imports;

use App\Models\Import;
use App\Models\ImportRow;
use App\Services\Accounts\AccountBalanceCalculatorService;
use Carbon\CarbonImmutable;

class JrePointReconciliationService
{
    public function __construct(
        private readonly AccountBalanceCalculatorService $accountBalanceCalculatorService,
    ) {}

    /**
     * @return array{
     *     captured_at:string,
     *     official_total:string,
     *     official_regular:string,
     *     official_limited:string,
     *     nearest_expiry:string|null,
     *     ledger_balance_before_import:string,
     *     import_balance_change:string,
     *     expected_balance_after_import:string,
     *     difference:string,
     *     is_initial_import:bool,
     *     recommended_initial_balance:string|null
     * }|null
     */
    public function handle(Import $import): ?array
    {
        if ($import->source_name !== 'jre_point' || $import->account === null) {
            return null;
        }

        $metadata = $import->source_metadata;
        $balance = is_array($metadata) ? ($metadata['balance'] ?? null) : null;
        $capturedAtValue = is_array($metadata) ? ($metadata['captured_at'] ?? null) : null;

        if (! is_array($balance) || ! is_numeric($balance['total'] ?? null) || ! is_string($capturedAtValue)) {
            return null;
        }

        $capturedAt = CarbonImmutable::parse($capturedAtValue);
        $officialTotal = number_format((int) $balance['total'], 2, '.', '');
        $officialLimited = number_format((int) ($balance['limited'] ?? 0), 2, '.', '');
        $officialRegular = number_format((int) ($balance['regular'] ?? ((int) $balance['total'] - (int) ($balance['limited'] ?? 0))), 2, '.', '');
        $ledgerBalance = $this->accountBalanceCalculatorService->calculate(
            $import->account,
            $capturedAt->toDateString(),
        );
        $importBalanceChange = $import->status === 'imported'
            ? '0.00'
            : $this->importBalanceChange($import);
        $expectedBalance = $this->accountBalanceCalculatorService->add($ledgerBalance, $importBalanceChange);
        $difference = $this->accountBalanceCalculatorService->subtract($expectedBalance, $officialTotal);
        $isInitialImport = ! Import::query()
            ->where('user_id', $import->user_id)
            ->where('account_id', $import->account_id)
            ->where('source_name', 'jre_point')
            ->where('status', 'imported')
            ->whereKeyNot($import->id)
            ->exists();
        $recommendedInitialBalance = $isInitialImport
            ? $this->accountBalanceCalculatorService->subtract((string) $import->account->initial_balance, $difference)
            : null;

        return [
            'captured_at' => $capturedAt->format('Y-m-d H:i:s'),
            'official_total' => $officialTotal,
            'official_regular' => $officialRegular,
            'official_limited' => $officialLimited,
            'nearest_expiry' => is_string($balance['nearest_expiry'] ?? null) ? $balance['nearest_expiry'] : null,
            'ledger_balance_before_import' => $ledgerBalance,
            'import_balance_change' => $importBalanceChange,
            'expected_balance_after_import' => $expectedBalance,
            'difference' => $difference,
            'is_initial_import' => $isInitialImport,
            'recommended_initial_balance' => $recommendedInitialBalance,
        ];
    }

    private function importBalanceChange(Import $import): string
    {
        $change = '0.00';
        $import->loadMissing('importRows');

        foreach ($import->importRows as $importRow) {
            if (
                $importRow->status !== 'ready'
                || $importRow->is_duplicate_candidate
                || ! $importRow->resolved_affects_account_balance
            ) {
                continue;
            }

            $signedAmount = $this->signedAmount($import, $importRow);
            $change = $this->accountBalanceCalculatorService->add($change, $signedAmount);
        }

        return $change;
    }

    private function signedAmount(Import $import, ImportRow $importRow): string
    {
        $amount = (string) ($importRow->amount ?? '0');

        if ($importRow->detected_type === 'income') {
            return $amount;
        }

        if ($importRow->detected_type === 'expense') {
            return '-'.$amount;
        }

        if ($importRow->detected_type === 'transfer') {
            if ($importRow->resolved_account_id === $import->account_id) {
                return '-'.$amount;
            }

            if ($importRow->resolved_transfer_account_id === $import->account_id) {
                return $amount;
            }
        }

        return '0.00';
    }
}
