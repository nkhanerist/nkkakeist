<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Dashboard\WeeklyImportStatusService;
use Illuminate\Console\Command;

class ReportWeeklyImportStatus extends Command
{
    protected $signature = 'imports:weekly-status
                            {--user= : 確認対象のユーザーIDまたはメールアドレス}';

    protected $description = 'JRE POINTとモバイルSuicaの週次取込状況を読み取り専用で表示します';

    public function handle(WeeklyImportStatusService $statusService): int
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
            ['ユーザーID', '対象週', 'JRE POINT', 'モバイルSuica', 'JRE最終更新', 'Suica最終更新'],
            $users->map(function (User $user) use ($statusService): array {
                $status = $statusService->handle($user);

                return [
                    $user->id,
                    $status['week_start'].'〜'.$status['week_end'],
                    $status['sources']['jre_point']['state'],
                    $status['sources']['mobile_suica']['state'],
                    $status['sources']['jre_point']['last_updated_at'] ?? '-',
                    $status['sources']['mobile_suica']['last_updated_at'] ?? '-',
                ];
            })->all(),
        );

        return self::SUCCESS;
    }
}
