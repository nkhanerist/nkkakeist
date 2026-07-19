<?php

namespace App\Services\Dashboard;

use App\Models\Account;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

class MonthlyClosingService
{
    /**
     * @param  array{uncategorized_count: int, unconfirmed_count: int, pending_import_count: int}  $quality
     * @return array<string, mixed>
     */
    public function build(User $user, CarbonImmutable $month, array $quality): array
    {
        $monthStart = $month->startOfMonth();
        $closing = $user->monthlyClosings()
            ->with('accountConfirmations')
            ->whereDate('month', $monthStart->toDateString())
            ->first();
        $confirmations = $closing?->accountConfirmations->keyBy('account_id') ?? collect();
        $requiredAccounts = $user->accounts()
            ->where('is_active', true)
            ->where('monthly_close_required', true)
            ->orderBy('display_order')
            ->orderBy('id')
            ->get();

        $accounts = $requiredAccounts->map(function (Account $account) use ($confirmations, $monthStart): array {
            $confirmation = $confirmations->get($account->id);
            $hasChanges = $confirmation !== null
                && $confirmation->data_fingerprint !== $this->accountFingerprint($account, $monthStart);

            return [
                'id' => $account->id,
                'name' => $account->name,
                'type' => $account->type,
                'currency' => $account->currency,
                'state' => $confirmation === null
                    ? 'unconfirmed'
                    : ($hasChanges ? 'changed' : 'confirmed'),
                'confirmed_at' => $confirmation?->confirmed_at?->toIso8601String(),
            ];
        })->values();

        $hasChanges = $closing?->data_fingerprint !== null
            && $closing->data_fingerprint !== $this->monthlyFingerprint($user, $monthStart);
        $status = $closing?->status ?? 'open';
        $monthEnded = $monthStart->endOfMonth()->isBefore(CarbonImmutable::today());
        $blockers = [];

        if (! $monthEnded) {
            $blockers[] = __('dashboard.closing.blockers.month_not_ended');
        }

        if ($quality['uncategorized_count'] > 0) {
            $blockers[] = __('dashboard.closing.blockers.uncategorized', [
                'count' => $quality['uncategorized_count'],
            ]);
        }

        if ($quality['unconfirmed_count'] > 0) {
            $blockers[] = __('dashboard.closing.blockers.unconfirmed', [
                'count' => $quality['unconfirmed_count'],
            ]);
        }

        if ($quality['pending_import_count'] > 0) {
            $blockers[] = __('dashboard.closing.blockers.pending_imports', [
                'count' => $quality['pending_import_count'],
            ]);
        }

        if ($accounts->isEmpty()) {
            $blockers[] = __('dashboard.closing.blockers.no_accounts');
        }

        $unconfirmedAccountCount = $accounts->where('state', 'unconfirmed')->count();
        $changedAccountCount = $accounts->where('state', 'changed')->count();

        if ($unconfirmedAccountCount > 0) {
            $blockers[] = __('dashboard.closing.blockers.unconfirmed_accounts', [
                'count' => $unconfirmedAccountCount,
            ]);
        }

        if ($changedAccountCount > 0) {
            $blockers[] = __('dashboard.closing.blockers.changed_accounts', [
                'count' => $changedAccountCount,
            ]);
        }

        if ($status === 'open') {
            $blockers[] = __('dashboard.closing.blockers.report_not_reviewed');
        } elseif ($status === 'reviewed' && $hasChanges) {
            $blockers[] = __('dashboard.closing.blockers.changed_after_review');
        }

        return [
            'status' => $status,
            'status_label' => __("dashboard.closing.status.{$status}"),
            'note' => $closing?->note ?? '',
            'reviewed_at' => $closing?->reviewed_at?->toIso8601String(),
            'closed_at' => $closing?->closed_at?->toIso8601String(),
            'last_reopened_at' => $closing?->last_reopened_at?->toIso8601String(),
            'last_reopen_reason' => $closing?->last_reopen_reason,
            'has_changes_since_review' => $hasChanges,
            'month_ended' => $monthEnded,
            'can_close' => $status === 'reviewed' && $blockers === [],
            'blockers' => array_values(array_unique($blockers)),
            'accounts' => $accounts->all(),
        ];
    }

    /**
     * @return array{uncategorized_count: int, unconfirmed_count: int, pending_import_count: int}
     */
    public function quality(User $user, CarbonImmutable $month): array
    {
        $monthStart = $month->startOfMonth();
        $monthEnd = $monthStart->endOfMonth();

        return [
            'uncategorized_count' => $user->transactions()
                ->where('is_calculation_target', true)
                ->whereIn('type', ['income', 'expense'])
                ->whereNull('category_id')
                ->whereBetween('transaction_date', [$monthStart->startOfDay(), $monthEnd->endOfDay()])
                ->count(),
            'unconfirmed_count' => $user->transactions()
                ->where('is_confirmed', false)
                ->whereBetween('transaction_date', [$monthStart->startOfDay(), $monthEnd->endOfDay()])
                ->count(),
            'pending_import_count' => $user->imports()
                ->whereIn('status', ['uploaded', 'parsed', 'validated'])
                ->count(),
        ];
    }

    public function monthlyFingerprint(User $user, CarbonImmutable $month): string
    {
        $monthStart = $month->startOfMonth();
        $monthEnd = $monthStart->endOfMonth();

        return $this->hash([
            'accounts' => $user->accounts()
                ->orderBy('id')
                ->get([
                    'id', 'type', 'balance_role', 'balance_method', 'include_in_net_worth',
                    'monthly_close_required', 'currency', 'initial_balance', 'opening_balance_date',
                    'is_active', 'updated_at',
                ])->toArray(),
            'transactions' => $user->transactions()
                ->withTrashed()
                ->whereBetween('transaction_date', [$monthStart->startOfDay(), $monthEnd->endOfDay()])
                ->orderBy('id')
                ->get($this->transactionFingerprintColumns())
                ->toArray(),
            'account_snapshots' => $user->accountSnapshots()
                ->whereBetween('captured_at', [$monthStart->startOfDay(), $monthEnd->endOfDay()])
                ->orderBy('id')
                ->get(['id', 'account_id', 'captured_at', 'purpose', 'balance', 'source_name', 'updated_at'])
                ->toArray(),
            'position_snapshots' => $user->investmentPositionSnapshots()
                ->whereBetween('captured_at', [$monthStart->startOfDay(), $monthEnd->endOfDay()])
                ->orderBy('id')
                ->get([
                    'id', 'account_id', 'captured_at', 'position_key', 'instrument_name',
                    'quantity', 'unit_price', 'acquisition_cost', 'valuation', 'unrealized_gain', 'updated_at',
                ])->toArray(),
            'asset_history_snapshots' => $user->assetHistorySnapshots()
                ->whereBetween('captured_on', [$monthStart->toDateString(), $monthEnd->toDateString()])
                ->orderBy('id')
                ->get(['id', 'captured_on', 'total_assets', 'currency', 'source_name', 'breakdown', 'updated_at'])
                ->toArray(),
        ]);
    }

    public function accountFingerprint(Account $account, CarbonImmutable $month): string
    {
        $monthStart = $month->startOfMonth();
        $monthEnd = $monthStart->endOfMonth();

        return $this->hash([
            'account' => $account->only([
                'id', 'type', 'balance_role', 'balance_method', 'include_in_net_worth',
                'monthly_close_required', 'currency', 'initial_balance', 'opening_balance_date',
                'is_active', 'updated_at',
            ]),
            'transactions' => $account->user->transactions()
                ->withTrashed()
                ->whereBetween('transaction_date', [$monthStart->startOfDay(), $monthEnd->endOfDay()])
                ->where(function (Builder $query) use ($account): void {
                    $query->where('account_id', $account->id)
                        ->orWhere('transfer_account_id', $account->id);
                })
                ->orderBy('id')
                ->get($this->transactionFingerprintColumns())
                ->toArray(),
            'account_snapshots' => $account->snapshots()
                ->whereBetween('captured_at', [$monthStart->startOfDay(), $monthEnd->endOfDay()])
                ->orderBy('id')
                ->get(['id', 'captured_at', 'purpose', 'balance', 'source_name', 'updated_at'])
                ->toArray(),
            'position_snapshots' => $account->investmentPositionSnapshots()
                ->whereBetween('captured_at', [$monthStart->startOfDay(), $monthEnd->endOfDay()])
                ->orderBy('id')
                ->get([
                    'id', 'captured_at', 'position_key', 'instrument_name', 'quantity', 'unit_price',
                    'acquisition_cost', 'valuation', 'unrealized_gain', 'updated_at',
                ])->toArray(),
        ]);
    }

    /** @return array<int, string> */
    private function transactionFingerprintColumns(): array
    {
        return [
            'id', 'account_id', 'transfer_account_id', 'transaction_date', 'posted_at', 'type',
            'amount', 'currency', 'merchant_name', 'description', 'category_id', 'subcategory_id',
            'is_confirmed', 'is_calculation_target', 'affects_account_balance', 'updated_at', 'deleted_at',
        ];
    }

    /** @param array<string, mixed> $payload */
    private function hash(array $payload): string
    {
        return hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION));
    }
}
