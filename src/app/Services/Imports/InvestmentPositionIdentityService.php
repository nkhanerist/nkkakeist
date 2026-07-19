<?php

namespace App\Services\Imports;

use Illuminate\Support\Str;

class InvestmentPositionIdentityService
{
    /** @param array<string, mixed> $position */
    public function positionKey(array $position, string $source, ?string $sourceAccountName): string
    {
        $instrumentName = (string) ($position['instrument_name'] ?? '');
        $instrumentCode = is_string($position['instrument_code'] ?? null)
            ? $position['instrument_code']
            : null;
        $externalId = is_string($position['external_id'] ?? null)
            ? $position['external_id']
            : null;
        $currency = strtoupper((string) ($position['currency'] ?? 'JPY'));

        $identity = $this->isMoneyForwardPensionPosition(
            $position,
            $source,
            $sourceAccountName,
            $externalId,
        )
            ? 'money_forward:pension:'.$this->normalizeName($instrumentName)
            : ($externalId ?? $instrumentCode ?? $instrumentName);

        return hash('sha256', implode('|', [$identity, $currency]));
    }

    public function semanticKey(string $instrumentName, string $currency): string
    {
        return $this->normalizeName($instrumentName).'|'.strtoupper($currency);
    }

    /** @param array<string, mixed> $position */
    private function isMoneyForwardPensionPosition(
        array $position,
        string $source,
        ?string $sourceAccountName,
        ?string $externalId,
    ): bool {
        if ($source !== 'money_forward') {
            return false;
        }

        if (($position['asset_class'] ?? null) === 'defined_contribution_pension') {
            return true;
        }

        if ($externalId !== null && str_starts_with($externalId, 'money_forward:pension:')) {
            return true;
        }

        $normalizedAccountName = $this->normalizeName($sourceAccountName ?? '');

        return Str::contains($normalizedAccountName, ['年金', '確定拠出', 'jis&t']);
    }

    private function normalizeName(string $value): string
    {
        return Str::lower(Str::squish(mb_convert_kana($value, 'asKV', 'UTF-8')));
    }
}
