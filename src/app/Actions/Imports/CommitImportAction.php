<?php

namespace App\Actions\Imports;

use App\Models\Account;
use App\Models\AccountSnapshot;
use App\Models\Category;
use App\Models\Import;
use App\Models\ImportRow;
use App\Models\Subcategory;
use App\Models\Transaction;
use App\Services\Imports\JrePointReconciliationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CommitImportAction
{
    public function __construct(
        private readonly BuildImportPreviewAction $buildImportPreviewAction,
        private readonly JrePointReconciliationService $jrePointReconciliationService,
        private readonly CommitBalanceSnapshotImportAction $commitBalanceSnapshotImportAction,
    ) {}

    public function handle(Import $import): Import
    {
        if ($import->source_name === 'balance_snapshot') {
            return $this->commitBalanceSnapshotImportAction->handle($import);
        }

        if ($import->status === 'imported') {
            throw ValidationException::withMessages([
                'import' => 'すでに取込済みです。',
            ]);
        }

        if ($import->status !== 'validated') {
            throw ValidationException::withMessages([
                'import' => '取込を確定できるのはプレビュー完了済みの import のみです。',
            ]);
        }

        $import = $this->buildImportPreviewAction->handle($import);
        $import->loadMissing('user', 'account', 'importRows');

        if (
            $import->source_name === 'jre_point'
            && $import->importRows->contains(fn (ImportRow $importRow): bool => $importRow->status === 'error')
        ) {
            throw ValidationException::withMessages([
                'import' => 'JRE POINT取込に未解決の行があります。すべて解決してから確定してください。',
            ]);
        }

        $jrePointReconciliation = $this->jrePointReconciliationService->handle($import);

        DB::transaction(function () use ($import, $jrePointReconciliation): void {
            $importedRows = 0;
            $skippedRows = 0;
            $duplicateRows = 0;

            foreach ($import->importRows as $importRow) {
                if ($importRow->status !== 'ready') {
                    if ($importRow->status !== 'imported') {
                        $importRow->update(['status' => 'skipped']);
                    }
                    $skippedRows++;

                    continue;
                }

                if ($importRow->is_duplicate_candidate) {
                    $duplicateRows++;
                    $skippedRows++;
                    $importRow->update(['status' => 'skipped']);

                    continue;
                }

                if ($importRow->detected_type === 'transfer') {
                    $this->commitTransferRow($import, $importRow);
                } else {
                    $this->commitIncomeOrExpenseRow($import, $importRow);
                }

                $importRow->update(['status' => 'imported']);
                $importedRows++;
            }

            $import->update([
                'status' => 'imported',
                'imported_rows' => $importedRows,
                'skipped_rows' => $skippedRows,
                'duplicate_rows' => $duplicateRows,
                'imported_at' => now(),
                'error_message' => null,
            ]);

            $this->commitJrePointSnapshot($import, $jrePointReconciliation);
        });

        return $import->fresh(['account', 'importRows']);
    }

    private function commitTransferRow(Import $import, ImportRow $importRow): void
    {
        $account = $this->resolveAccount($import, $importRow);
        $transferAccount = $this->resolveTransferAccount($importRow);

        if ($account === null || $transferAccount === null) {
            throw ValidationException::withMessages([
                'import' => '振替行の相手口座を確定できないため取込できません。',
            ]);
        }

        if ($account->currency !== $transferAccount->currency) {
            throw ValidationException::withMessages([
                'import' => '振替元口座と振替先口座は同じ通貨である必要があります。',
            ]);
        }

        $importRow->update([
            'resolved_account_id' => $account->id,
            'resolved_transfer_account_id' => $transferAccount->id,
            'resolved_category_id' => null,
            'resolved_subcategory_id' => null,
        ]);

        Transaction::create([
            'user_id' => $import->user_id,
            'account_id' => $account->id,
            'transfer_account_id' => $transferAccount->id,
            'transaction_date' => $importRow->transaction_date,
            'posted_at' => null,
            'type' => 'transfer',
            'amount' => $importRow->amount,
            'currency' => $account->currency,
            'merchant_name' => $importRow->merchant_name,
            'description' => $importRow->description,
            'category_id' => null,
            'subcategory_id' => null,
            'payment_method_label' => null,
            'external_id' => $this->externalId($importRow),
            'import_id' => $import->id,
            'import_row_id' => $importRow->id,
            'duplicate_hash' => $importRow->duplicate_hash,
            'is_confirmed' => true,
            'is_calculation_target' => false,
            'affects_account_balance' => true,
            'memo' => $importRow->raw_payload['メモ'] ?? null,
        ]);
    }

    private function commitIncomeOrExpenseRow(Import $import, ImportRow $importRow): void
    {
        $account = $this->resolveAccount($import, $importRow);
        $category = $this->resolveCategory($import, $importRow);
        $subcategory = $this->resolveSubcategory($import, $importRow, $category);

        $importRow->update([
            'resolved_account_id' => $account?->id,
            'resolved_category_id' => $category?->id,
            'resolved_subcategory_id' => $subcategory?->id,
            'resolved_transfer_account_id' => null,
        ]);

        Transaction::create([
            'user_id' => $import->user_id,
            'account_id' => $account?->id,
            'transfer_account_id' => null,
            'transaction_date' => $importRow->transaction_date,
            'posted_at' => null,
            'type' => $importRow->detected_type,
            'amount' => $importRow->amount,
            'currency' => $account?->currency ?? $import->account?->currency ?? 'JPY',
            'merchant_name' => $importRow->merchant_name,
            'description' => $importRow->description,
            'category_id' => $category?->id,
            'subcategory_id' => $subcategory?->id,
            'payment_method_label' => null,
            'external_id' => $this->externalId($importRow),
            'import_id' => $import->id,
            'import_row_id' => $importRow->id,
            'duplicate_hash' => $importRow->duplicate_hash,
            'is_confirmed' => true,
            'is_calculation_target' => $importRow->resolved_is_calculation_target ?? true,
            'affects_account_balance' => $importRow->resolved_affects_account_balance ?? true,
            'memo' => $importRow->raw_payload['メモ'] ?? null,
        ]);
    }

    private function resolveAccount(Import $import, ImportRow $importRow): ?Account
    {
        if ($importRow->resolved_account_id !== null) {
            return Account::query()->find($importRow->resolved_account_id);
        }

        if ($import->account_id !== null) {
            return $import->account;
        }

        if ($importRow->account_name === null) {
            return null;
        }

        return Account::query()->firstOrCreate(
            [
                'user_id' => $import->user_id,
                'name' => $importRow->account_name,
            ],
            [
                'type' => 'other',
                'currency' => $import->account?->currency ?? 'JPY',
                'initial_balance' => '0.00',
                'display_order' => $this->nextAccountDisplayOrder($import),
                'is_active' => true,
                'note' => null,
            ],
        );
    }

    private function resolveCategory(Import $import, ImportRow $importRow): ?Category
    {
        if ($importRow->resolved_category_id !== null) {
            return Category::query()->find($importRow->resolved_category_id);
        }

        if ($importRow->category_name === null || ! in_array($importRow->detected_type, ['income', 'expense'], true)) {
            return null;
        }

        return Category::query()->firstOrCreate(
            [
                'user_id' => $import->user_id,
                'name' => $importRow->category_name,
            ],
            [
                'type' => $importRow->detected_type,
                'color' => null,
                'icon' => null,
                'display_order' => $this->nextCategoryDisplayOrder($import),
                'is_active' => true,
            ],
        );
    }

    private function resolveTransferAccount(ImportRow $importRow): ?Account
    {
        if ($importRow->resolved_transfer_account_id === null) {
            return null;
        }

        return Account::query()->find($importRow->resolved_transfer_account_id);
    }

    private function resolveSubcategory(
        Import $import,
        ImportRow $importRow,
        ?Category $category,
    ): ?Subcategory {
        if ($importRow->resolved_subcategory_id !== null) {
            return Subcategory::query()->find($importRow->resolved_subcategory_id);
        }

        if ($category === null || $importRow->subcategory_name === null) {
            return null;
        }

        return Subcategory::query()->firstOrCreate(
            [
                'user_id' => $import->user_id,
                'category_id' => $category->id,
                'name' => $importRow->subcategory_name,
            ],
            [
                'display_order' => $this->nextSubcategoryDisplayOrder($category),
                'is_active' => true,
            ],
        );
    }

    private function nextAccountDisplayOrder(Import $import): int
    {
        return (int) Account::query()
            ->where('user_id', $import->user_id)
            ->max('display_order') + 1;
    }

    private function nextCategoryDisplayOrder(Import $import): int
    {
        return (int) Category::query()
            ->where('user_id', $import->user_id)
            ->max('display_order') + 1;
    }

    private function nextSubcategoryDisplayOrder(Category $category): int
    {
        return (int) Subcategory::query()
            ->where('category_id', $category->id)
            ->max('display_order') + 1;
    }

    private function externalId(ImportRow $importRow): ?string
    {
        $externalId = $importRow->raw_payload['ID'] ?? $importRow->raw_payload['id'] ?? null;

        if (! is_string($externalId)) {
            return null;
        }

        $normalized = trim($externalId);

        return $normalized === '' ? null : $normalized;
    }

    /** @param array<string, mixed>|null $reconciliation */
    private function commitJrePointSnapshot(Import $import, ?array $reconciliation): void
    {
        if ($reconciliation === null || $import->account === null) {
            return;
        }

        $previousInitialBalance = (string) $import->account->initial_balance;

        if (
            $reconciliation['is_initial_import']
            && is_string($reconciliation['recommended_initial_balance'])
        ) {
            $import->account->update([
                'initial_balance' => $reconciliation['recommended_initial_balance'],
            ]);
        }

        AccountSnapshot::query()->updateOrCreate(
            ['import_id' => $import->id],
            [
                'user_id' => $import->user_id,
                'account_id' => $import->account_id,
                'captured_at' => $reconciliation['captured_at'],
                'balance' => $reconciliation['official_total'],
                'source_name' => 'jre_point',
                'metadata' => [
                    'regular_points' => $reconciliation['official_regular'],
                    'limited_points' => $reconciliation['official_limited'],
                    'nearest_expiry' => $reconciliation['nearest_expiry'],
                    'initial_balance_rebased' => $reconciliation['is_initial_import'],
                    'previous_initial_balance' => $previousInitialBalance,
                ],
            ],
        );
    }
}
