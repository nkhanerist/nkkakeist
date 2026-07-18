<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Dashboard\DailySnapshotStatusService;
use Illuminate\Console\Command;

class ReportMoneyForwardDailyStatus extends Command
{
    protected $signature = 'money-forward:daily-status
                            {--user= : 確認対象のユーザーIDまたはメールアドレス}';

    protected $description = 'Money Forwardの日次残高・銘柄・総資産の取得状況を読み取り専用で表示します';

    public function handle(DailySnapshotStatusService $statusService): int
    {
        $users = User::query()
            ->when($this->option('user'), function ($query, string $user): void {
                $query->where(function ($query) use ($user): void {
                    $query->where('email', $user);

                    if (ctype_digit($user)) {
                        $query->orWhere('id', (int) $user);
                    }
                });
            })
            ->orderBy('id')
            ->get();

        if ($users->isEmpty()) {
            $this->error('確認対象のユーザーが見つかりません。');

            return self::FAILURE;
        }

        $this->table(
            ['ユーザーID', '対象日', '状態', '口座', '銘柄', '総資産', '最終取込'],
            $users->map(function (User $user) use ($statusService): array {
                $status = $statusService->handle($user);

                return [
                    $user->id,
                    $status['date'],
                    $status['state'],
                    $status['account_count'],
                    $status['position_count'],
                    $status['asset_history_recorded'] ? 'recorded' : 'missing',
                    $status['last_imported_at'] ?? '-',
                ];
            })->all(),
        );

        return self::SUCCESS;
    }
}
