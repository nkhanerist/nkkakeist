<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Accounts\ReconcileAssetTransferFlowsService;
use Illuminate\Console\Command;

class ReconcileAssetTransferFlows extends Command
{
    protected $signature = 'accounts:reconcile-asset-flows
        {--user= : 対象 user id}
        {--apply : 補正をDBへ適用する}';

    protected $description = 'Kyashの鏡像チャージとTHEO積立経路を診断し、明示指定時だけ補正します。';

    public function __construct(
        private readonly ReconcileAssetTransferFlowsService $reconcileAssetTransferFlowsService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $userId = $this->option('user');

        if ($userId === null || ! is_numeric($userId) || (int) $userId <= 0) {
            $this->error('--user は正の整数で指定してください。');

            return self::INVALID;
        }

        $user = User::query()->find((int) $userId);

        if ($user === null) {
            $this->error('対象ユーザーが見つかりません。');

            return self::FAILURE;
        }

        $apply = (bool) $this->option('apply');
        $report = $this->reconcileAssetTransferFlowsService->handle($user, $apply);

        $this->table(
            ['補正内容', '件数', '金額'],
            [
                ['Kyash鏡像チャージのスキップ', $report['kyash_duplicate_count'], $report['kyash_duplicate_amount'].' JPY'],
                ['dカード積立のd払いへの付替', $report['theo_reroute_count'], $report['theo_reroute_amount'].' JPY'],
                ['THEO積立別名の移動', $report['aliases_need_update'] ? 1 : 0, '-'],
            ],
        );

        if (! $apply) {
            $this->warn('read-only診断です。適用する場合はDBバックアップ後に --apply を付けて実行してください。');

            return self::SUCCESS;
        }

        $this->info('資産移動の補正を適用しました。');
        $this->table(
            ['口座', '補正前残高', '補正後残高'],
            collect($report['balances_before'])
                ->map(fn (string $balance, string $name): array => [
                    $name,
                    $balance,
                    $report['balances_after'][$name] ?? '-',
                ])
                ->values()
                ->all(),
        );

        return self::SUCCESS;
    }
}
