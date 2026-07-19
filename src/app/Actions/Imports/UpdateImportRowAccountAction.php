<?php

namespace App\Actions\Imports;

use App\Http\Requests\Imports\UpdateImportRowAccountRequest;
use App\Models\Account;
use App\Models\Import;
use App\Models\ImportRow;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateImportRowAccountAction
{
    public function __construct(
        private readonly BuildImportPreviewAction $buildImportPreviewAction,
    ) {}

    public function handle(
        Import $import,
        ImportRow $importRow,
        ?int $accountId,
        bool $rememberMapping = false,
    ): Import {
        $errorKey = UpdateImportRowAccountRequest::errorKey($importRow);

        if ($import->status === 'imported') {
            throw ValidationException::withMessages([
                $errorKey => trans('imports.messages.imported_not_editable'),
            ]);
        }

        if ($import->source_name !== 'balance_snapshot' || $importRow->import_id !== $import->id) {
            throw ValidationException::withMessages([
                $errorKey => trans('imports.messages.balance_account_only'),
            ]);
        }

        return DB::transaction(function () use (
            $import,
            $importRow,
            $accountId,
            $rememberMapping,
            $errorKey,
        ): Import {
            if ($rememberMapping && $accountId !== null) {
                $this->rememberMapping($import, $importRow, $accountId, $errorKey);
            }

            $importRow->update([
                'manual_resolved_account_id' => $accountId,
                'replace_account_snapshot_id' => null,
            ]);

            return $this->buildImportPreviewAction->handle($import->fresh());
        });
    }

    private function rememberMapping(
        Import $import,
        ImportRow $importRow,
        int $accountId,
        string $errorKey,
    ): void {
        $sourceAccountName = trim((string) $importRow->account_name);
        $account = $import->user->accounts()
            ->whereKey($accountId)
            ->where('is_active', true)
            ->first();

        if ($sourceAccountName === '' || ! $account instanceof Account) {
            throw ValidationException::withMessages([
                $errorKey => trans('imports.messages.mapping_unavailable'),
            ]);
        }

        $normalizedSourceName = $this->normalizeText($sourceAccountName);
        $conflictingAccount = $import->user->accounts()
            ->where('is_active', true)
            ->get()
            ->first(function (Account $candidate) use ($account, $normalizedSourceName): bool {
                if ($candidate->id === $account->id) {
                    return false;
                }

                return collect([$candidate->name, ...($candidate->import_aliases ?? [])])
                    ->filter(fn ($value): bool => is_string($value) && trim($value) !== '')
                    ->map(fn (string $value): string => $this->normalizeText($value))
                    ->contains($normalizedSourceName);
            });

        if ($conflictingAccount instanceof Account) {
            throw ValidationException::withMessages([
                $errorKey => trans('imports.messages.mapping_conflict', [
                    'account' => $conflictingAccount->name,
                ]),
            ]);
        }

        $knownNames = collect([$account->name, ...($account->import_aliases ?? [])])
            ->filter(fn ($value): bool => is_string($value) && trim($value) !== '')
            ->map(fn (string $value): string => $this->normalizeText($value));

        if ($knownNames->contains($normalizedSourceName)) {
            return;
        }

        $account->update([
            'import_aliases' => [
                ...($account->import_aliases ?? []),
                $sourceAccountName,
            ],
        ]);
    }

    private function normalizeText(string $value): string
    {
        return mb_strtolower(trim(mb_convert_kana($value, 'asKV', 'UTF-8')), 'UTF-8');
    }
}
