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
                $errorKey => '取込済みの import は再編集できません。',
            ]);
        }

        if ($import->source_name !== 'balance_snapshot' || $importRow->import_id !== $import->id) {
            throw ValidationException::withMessages([
                $errorKey => '公式残高の取込行だけ取込先口座を更新できます。',
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
                $errorKey => '取得元の口座名または取込先口座を確認できないため、対応を記憶できません。',
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
                $errorKey => "この取得名は「{$conflictingAccount->name}」に設定済みです。口座の取込用別名を確認してください。",
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
