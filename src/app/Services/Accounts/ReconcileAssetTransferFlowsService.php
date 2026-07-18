<?php

namespace App\Services\Accounts;

use App\Models\Account;
use App\Models\Import;
use App\Models\ImportRow;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ReconcileAssetTransferFlowsService
{
    private const THEO_CARD_MERCHANT = 'THEO積立/SMBC日興証券';

    public function __construct(
        private readonly AccountBalanceCalculatorService $accountBalanceCalculatorService,
    ) {}

    /**
     * @return array{
     *     user_id:int,
     *     kyash_duplicate_count:int,
     *     kyash_duplicate_amount:string,
     *     theo_reroute_count:int,
     *     theo_reroute_amount:string,
     *     aliases_need_update:bool,
     *     balances_before:array<string, string>,
     *     balances_after:array<string, string>
     * }
     */
    public function handle(User $user, bool $apply = false): array
    {
        $accounts = $user->accounts()->get();
        $dCard = $this->findAccount($accounts, 'dカード');
        $dPay = $this->findAccount($accounts, 'd払い');
        $kyash = $this->findAccount($accounts, 'kyash');
        $theo = $this->findAccount($accounts, 'THEO');

        $kyashDuplicates = $this->kyashDuplicateTransfers($user, $kyash, $dCard);
        $theoReroutes = $this->theoTransfersToReroute($user, $dCard, $dPay, $theo);
        $aliasesNeedUpdate = $this->aliasesNeedUpdate($dCard, $dPay);
        $balancesBefore = $this->balances([$dCard, $dPay, $kyash, $theo]);

        if ($apply && ($kyashDuplicates->isNotEmpty() || $theoReroutes->isNotEmpty() || $aliasesNeedUpdate)) {
            DB::transaction(function () use (
                $kyashDuplicates,
                $theoReroutes,
                $dCard,
                $dPay,
            ): void {
                $affectedImportIds = collect();

                foreach ($kyashDuplicates as $transaction) {
                    $this->skipMirrorImportRow($transaction);
                    $transaction->delete();

                    if ($transaction->import_id !== null) {
                        $affectedImportIds->push($transaction->import_id);
                    }
                }

                foreach ($theoReroutes as $transaction) {
                    $transaction->update([
                        'transfer_account_id' => $dPay?->id,
                    ]);

                    $this->rerouteImportRowToDpay($transaction, $dPay);
                }

                $this->updateTheoAlias($dCard, $dPay);

                $affectedImportIds
                    ->unique()
                    ->each(fn (int $importId) => $this->refreshImportCounts($importId));
            });
        }

        foreach ([$dCard, $dPay, $kyash, $theo] as $account) {
            $account?->refresh();
        }

        return [
            'user_id' => $user->id,
            'kyash_duplicate_count' => $kyashDuplicates->count(),
            'kyash_duplicate_amount' => $this->sumAmounts($kyashDuplicates),
            'theo_reroute_count' => $theoReroutes->count(),
            'theo_reroute_amount' => $this->sumAmounts($theoReroutes),
            'aliases_need_update' => $aliasesNeedUpdate,
            'balances_before' => $balancesBefore,
            'balances_after' => $apply
                ? $this->balances([$dCard, $dPay, $kyash, $theo])
                : $balancesBefore,
        ];
    }

    /**
     * @param  EloquentCollection<int, Account>  $accounts
     */
    private function findAccount(EloquentCollection $accounts, string $name): ?Account
    {
        return $accounts->first(
            fn (Account $account): bool => mb_strtolower($account->name) === mb_strtolower($name),
        );
    }

    /**
     * @return EloquentCollection<int, Transaction>
     */
    private function kyashDuplicateTransfers(User $user, ?Account $kyash, ?Account $dCard): EloquentCollection
    {
        if ($kyash === null || $dCard === null) {
            return new EloquentCollection;
        }

        $canonicalKeys = $user->transactions()
            ->where('type', 'transfer')
            ->where('account_id', $dCard->id)
            ->where('transfer_account_id', $kyash->id)
            ->get(['transaction_date', 'amount'])
            ->mapWithKeys(fn (Transaction $transaction): array => [
                $this->dateAmountKey($transaction) => true,
            ]);

        return $user->transactions()
            ->where('type', 'transfer')
            ->where('account_id', $kyash->id)
            ->where('transfer_account_id', $dCard->id)
            ->where('merchant_name', 'like', 'カード MasterCard%')
            ->get()
            ->filter(fn (Transaction $transaction): bool => $canonicalKeys->has(
                $this->dateAmountKey($transaction),
            ))
            ->values();
    }

    /**
     * @return EloquentCollection<int, Transaction>
     */
    private function theoTransfersToReroute(
        User $user,
        ?Account $dCard,
        ?Account $dPay,
        ?Account $theo,
    ): EloquentCollection {
        if ($dCard === null || $dPay === null || $theo === null) {
            return new EloquentCollection;
        }

        return $user->transactions()
            ->where('type', 'transfer')
            ->where('account_id', $dCard->id)
            ->where('transfer_account_id', $theo->id)
            ->where('merchant_name', self::THEO_CARD_MERCHANT)
            ->get();
    }

    private function aliasesNeedUpdate(?Account $dCard, ?Account $dPay): bool
    {
        if ($dCard === null || $dPay === null) {
            return false;
        }

        return in_array(self::THEO_CARD_MERCHANT, $dCard->import_aliases ?? [], true)
            || ! in_array(self::THEO_CARD_MERCHANT, $dPay->import_aliases ?? [], true);
    }

    private function updateTheoAlias(?Account $dCard, ?Account $dPay): void
    {
        if ($dCard === null || $dPay === null) {
            return;
        }

        $dCard->update([
            'import_aliases' => collect($dCard->import_aliases ?? [])
                ->reject(fn (string $alias): bool => $alias === self::THEO_CARD_MERCHANT)
                ->values()
                ->all(),
        ]);

        $dPay->update([
            'import_aliases' => collect([
                ...($dPay->import_aliases ?? []),
                self::THEO_CARD_MERCHANT,
            ])->unique()->values()->all(),
        ]);
    }

    private function skipMirrorImportRow(Transaction $transaction): void
    {
        $importRow = $transaction->importRow;

        if ($importRow === null) {
            return;
        }

        $validationErrors = collect($importRow->validation_errors ?? [])
            ->filter(fn ($error): bool => is_string($error))
            ->push('資産残高補正: カード側にも同一チャージがあるため鏡像行をスキップしました。')
            ->unique()
            ->values()
            ->all();

        $importRow->update([
            'status' => 'skipped',
            'is_duplicate_candidate' => true,
            'validation_errors' => $validationErrors,
        ]);
    }

    private function rerouteImportRowToDpay(Transaction $transaction, ?Account $dPay): void
    {
        $importRow = $transaction->importRow;

        if ($importRow === null || $dPay === null) {
            return;
        }

        $resolution = is_array($importRow->transfer_resolution)
            ? $importRow->transfer_resolution
            : [];

        $importRow->update([
            'resolved_transfer_account_id' => $dPay->id,
            'transfer_resolution' => [
                ...$resolution,
                'destination_resolution_type' => 'exact_alias',
                'destination_resolution_message' => '振替先口座は取込用別名の完全一致で解決しました。',
                'unresolved_reason' => null,
            ],
        ]);
    }

    private function refreshImportCounts(int $importId): void
    {
        $import = Import::query()->find($importId);

        if ($import === null) {
            return;
        }

        $rows = ImportRow::query()->where('import_id', $import->id);

        $import->update([
            'imported_rows' => (clone $rows)->where('status', 'imported')->count(),
            'skipped_rows' => (clone $rows)->where('status', 'skipped')->count(),
            'duplicate_rows' => (clone $rows)
                ->where('status', 'skipped')
                ->where('is_duplicate_candidate', true)
                ->count(),
        ]);
    }

    private function dateAmountKey(Transaction $transaction): string
    {
        return $transaction->transaction_date->toDateString().'|'.(string) $transaction->amount;
    }

    /**
     * @param  Collection<int, Transaction>  $transactions
     */
    private function sumAmounts(Collection $transactions): string
    {
        return number_format((float) $transactions->sum('amount'), 2, '.', '');
    }

    /**
     * @param  array<int, Account|null>  $accounts
     * @return array<string, string>
     */
    private function balances(array $accounts): array
    {
        $endDate = now()->toDateString();

        return collect($accounts)
            ->filter()
            ->mapWithKeys(fn (Account $account): array => [
                $account->name => $this->accountBalanceCalculatorService->calculate($account, $endDate),
            ])
            ->all();
    }
}
