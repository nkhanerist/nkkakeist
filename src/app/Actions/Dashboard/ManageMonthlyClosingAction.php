<?php

namespace App\Actions\Dashboard;

use App\Models\Account;
use App\Models\MonthlyClosing;
use App\Models\User;
use App\Services\Dashboard\MonthlyClosingService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ManageMonthlyClosingAction
{
    public function __construct(
        private readonly MonthlyClosingService $monthlyClosingService,
    ) {}

    public function saveNote(User $user, CarbonImmutable $month, ?string $note): void
    {
        $this->closing($user, $month)->update(['note' => $note]);
    }

    public function review(User $user, CarbonImmutable $month): void
    {
        $closing = $this->closing($user, $month);

        if ($closing->status === 'closed') {
            throw ValidationException::withMessages([
                'monthly_closing' => __('dashboard.closing.errors.closed_requires_reopen'),
            ]);
        }

        $closing->update([
            'status' => 'reviewed',
            'data_fingerprint' => $this->monthlyClosingService->monthlyFingerprint($user, $month),
            'reviewed_at' => now(),
        ]);
    }

    public function confirmAccount(User $user, CarbonImmutable $month, Account $account): void
    {
        if (! $account->is_active || ! $account->monthly_close_required) {
            throw ValidationException::withMessages([
                'account' => __('dashboard.closing.errors.account_not_required'),
            ]);
        }

        $closing = $this->closing($user, $month);

        if ($closing->status === 'closed') {
            throw ValidationException::withMessages([
                'account' => __('dashboard.closing.errors.closed_requires_reopen'),
            ]);
        }

        $closing->accountConfirmations()->updateOrCreate(
            ['account_id' => $account->id],
            [
                'user_id' => $user->id,
                'data_fingerprint' => $this->monthlyClosingService->accountFingerprint($account, $month),
                'confirmed_at' => now(),
            ],
        );
    }

    public function unconfirmAccount(User $user, CarbonImmutable $month, Account $account): void
    {
        $closing = $this->closing($user, $month);

        if ($closing->status === 'closed') {
            throw ValidationException::withMessages([
                'account' => __('dashboard.closing.errors.closed_requires_reopen'),
            ]);
        }

        $closing->accountConfirmations()
            ->where('user_id', $user->id)
            ->where('account_id', $account->id)
            ->delete();
    }

    public function close(User $user, CarbonImmutable $month): void
    {
        DB::transaction(function () use ($user, $month): void {
            $state = $this->monthlyClosingService->build(
                $user,
                $month,
                $this->monthlyClosingService->quality($user, $month),
            );

            if (! $state['can_close']) {
                throw ValidationException::withMessages([
                    'monthly_closing' => $state['blockers'],
                ]);
            }

            $this->closing($user, $month)->update([
                'status' => 'closed',
                'data_fingerprint' => $this->monthlyClosingService->monthlyFingerprint($user, $month),
                'closed_at' => now(),
            ]);
        });
    }

    public function reopen(User $user, CarbonImmutable $month, string $reason): void
    {
        $closing = $this->closing($user, $month);

        if ($closing->status !== 'closed') {
            throw ValidationException::withMessages([
                'monthly_closing' => __('dashboard.closing.errors.only_closed_can_reopen'),
            ]);
        }

        $closing->update([
            'status' => 'open',
            'data_fingerprint' => null,
            'reviewed_at' => null,
            'closed_at' => null,
            'last_reopened_at' => now(),
            'last_reopen_reason' => $reason,
        ]);
    }

    private function closing(User $user, CarbonImmutable $month): MonthlyClosing
    {
        $monthStart = $month->startOfMonth();
        $closing = $user->monthlyClosings()
            ->whereDate('month', $monthStart->toDateString())
            ->first();

        return $closing ?? $user->monthlyClosings()->create([
            'month' => $monthStart,
            'status' => 'open',
        ]);
    }
}
