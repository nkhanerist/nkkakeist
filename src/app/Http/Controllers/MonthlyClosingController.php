<?php

namespace App\Http\Controllers;

use App\Actions\Dashboard\ManageMonthlyClosingAction;
use App\Models\Account;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class MonthlyClosingController extends Controller
{
    public function __construct(
        private readonly ManageMonthlyClosingAction $manageMonthlyClosingAction,
    ) {}

    public function update(Request $request, string $month): RedirectResponse
    {
        $validated = $request->validate([
            'note' => ['nullable', 'string', 'max:5000'],
        ]);

        $this->manageMonthlyClosingAction->saveNote(
            $request->user(),
            $this->month($month),
            $validated['note'] ?? null,
        );

        return back()->with('success', __('dashboard.closing.messages.note_saved'));
    }

    public function review(Request $request, string $month): RedirectResponse
    {
        $this->manageMonthlyClosingAction->review($request->user(), $this->month($month));

        return back()->with('success', __('dashboard.closing.messages.reviewed'));
    }

    public function confirmAccount(Request $request, string $month, Account $account): RedirectResponse
    {
        $this->ensureOwnedAccount($request, $account);
        $this->manageMonthlyClosingAction->confirmAccount(
            $request->user(),
            $this->month($month),
            $account,
        );

        return back()->with('success', __('dashboard.closing.messages.account_confirmed', [
            'account' => $account->name,
        ]));
    }

    public function unconfirmAccount(Request $request, string $month, Account $account): RedirectResponse
    {
        $this->ensureOwnedAccount($request, $account);
        $this->manageMonthlyClosingAction->unconfirmAccount(
            $request->user(),
            $this->month($month),
            $account,
        );

        return back()->with('success', __('dashboard.closing.messages.account_unconfirmed', [
            'account' => $account->name,
        ]));
    }

    public function close(Request $request, string $month): RedirectResponse
    {
        $this->manageMonthlyClosingAction->close($request->user(), $this->month($month));

        return back()->with('success', __('dashboard.closing.messages.closed', [
            'month' => $month,
        ]));
    }

    public function reopen(Request $request, string $month): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        $this->manageMonthlyClosingAction->reopen(
            $request->user(),
            $this->month($month),
            $validated['reason'],
        );

        return back()->with('success', __('dashboard.closing.messages.reopened', [
            'month' => $month,
        ]));
    }

    private function month(string $value): CarbonImmutable
    {
        abort_unless(preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $value) === 1, 404);

        [$year, $month] = array_map('intval', explode('-', $value));

        return CarbonImmutable::create($year, $month, 1)->startOfMonth();
    }

    private function ensureOwnedAccount(Request $request, Account $account): void
    {
        abort_unless($account->user_id === $request->user()->id, 404);
    }
}
