<?php

namespace App\Services\Imports;

use App\Models\ImportRow;
use App\Models\User;
use Illuminate\Support\Str;

class DuplicateDetectionService
{
    public function buildHash(
        int $userId,
        string $transactionDate,
        string $amount,
        ?string $merchantName,
        ?string $description,
        string $type,
        ?int $accountId,
        ?string $externalId = null,
        ?int $transferAccountId = null,
    ): string {
        if ($type === 'transfer') {
            return hash('sha256', implode('|', [
                $userId,
                $transactionDate,
                $amount,
                $type,
                $accountId ?? '',
                $transferAccountId ?? '',
            ]));
        }

        $normalizedExternalId = $this->normalizeExternalId($externalId);

        if ($normalizedExternalId !== null) {
            return hash('sha256', implode('|', [
                $userId,
                'money_forward',
                $normalizedExternalId,
            ]));
        }

        $descriptor = $this->normalizeDescriptor($merchantName ?: $description);

        return hash('sha256', implode('|', [
            $userId,
            $transactionDate,
            $amount,
            $descriptor,
            $type,
            $accountId ?? '',
            $transferAccountId ?? '',
        ]));
    }

    public function isDuplicateCandidate(
        User $user,
        string $transactionDate,
        string $amount,
        ?string $merchantName,
        ?string $description,
        string $type,
        ?int $accountId,
        ?string $externalId = null,
        ?int $transferAccountId = null,
    ): bool {
        $hash = $this->buildHash(
            $user->id,
            $transactionDate,
            $amount,
            $merchantName,
            $description,
            $type,
            $accountId,
            $externalId,
            $transferAccountId,
        );

        $normalizedExternalId = $type === 'transfer'
            ? null
            : $this->normalizeExternalId($externalId);

        if ($normalizedExternalId !== null) {
            return $user->transactions()
                ->where('external_id', $normalizedExternalId)
                ->exists();
        }

        $candidates = $user->transactions()
            ->when($accountId !== null, fn ($query) => $query->where('account_id', $accountId))
            ->when($transferAccountId !== null, fn ($query) => $query->where('transfer_account_id', $transferAccountId))
            ->whereDate('transaction_date', $transactionDate)
            ->where('amount', $amount)
            ->where('type', $type)
            ->get(['merchant_name', 'description', 'duplicate_hash', 'external_id', 'account_id', 'transfer_account_id', 'transaction_date', 'amount', 'type']);

        foreach ($candidates as $candidate) {
            if ($candidate->duplicate_hash === $hash) {
                return true;
            }

            $candidateHash = $this->buildHash(
                $user->id,
                $candidate->transaction_date->format('Y-m-d'),
                (string) $candidate->amount,
                $candidate->merchant_name,
                $candidate->description,
                $candidate->type,
                $candidate->account_id,
                $candidate->external_id,
                $candidate->transfer_account_id,
            );

            if ($candidateHash === $hash) {
                return true;
            }
        }

        return false;
    }

    public function isImportRowDuplicateCandidate(
        User $user,
        string $duplicateHash,
        ?int $currentImportId = null,
    ): bool {
        return ImportRow::query()
            ->where('duplicate_hash', $duplicateHash)
            ->when(
                $currentImportId !== null,
                fn ($query) => $query->where('import_id', '!=', $currentImportId),
            )
            ->whereHas('import', fn ($query) => $query
                ->where('user_id', $user->id)
                ->where('status', 'imported'))
            ->exists();
    }

    public function countTransferCandidates(
        User $user,
        string $transactionDate,
        string $amount,
        ?int $accountId,
        ?int $transferAccountId,
    ): int {
        return $user->transactions()
            ->when($accountId !== null, fn ($query) => $query->where('account_id', $accountId))
            ->when($transferAccountId !== null, fn ($query) => $query->where('transfer_account_id', $transferAccountId))
            ->whereDate('transaction_date', $transactionDate)
            ->where('amount', $amount)
            ->where('type', 'transfer')
            ->count();
    }

    private function normalizeDescriptor(?string $value): string
    {
        return Str::lower(Str::squish((string) ($value ?? '')));
    }

    private function normalizeExternalId(?string $value): ?string
    {
        $normalized = trim((string) ($value ?? ''));

        return $normalized === '' ? null : $normalized;
    }
}
