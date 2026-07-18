<?php

namespace App\Services\Imports;

use App\Models\Import;
use App\Models\User;

class ImportOptionsService
{
    /**
     * @return array<int, array{value: string, label: string}>
     */
    public function sourceOptions(): array
    {
        return [
            ['value' => 'money_forward', 'label' => 'Money Forward'],
            ['value' => 'mobile_suica', 'label' => 'モバイルSuica PDF'],
            ['value' => 'jre_point', 'label' => 'JRE POINT 履歴'],
            ['value' => 'balance_snapshot', 'label' => '公式残高・評価額'],
            ['value' => 'asset_history', 'label' => 'Money Forward 資産推移'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function sourceLabels(): array
    {
        return collect($this->sourceOptions())
            ->pluck('label', 'value')
            ->all();
    }

    /**
     * @return array<string, string>
     */
    public function statusLabels(): array
    {
        return [
            'uploaded' => 'アップロード済み',
            'parsed' => '解析済み',
            'validated' => '確認待ち',
            'imported' => '取込済み',
            'failed' => '失敗',
        ];
    }

    /**
     * @return array<int, array{id: int, name: string, type: string, currency: string, balance_role: string, balance_method: string, is_active: bool}>
     */
    public function accountOptions(User $user): array
    {
        return $user->accounts()
            ->orderBy('display_order')
            ->orderBy('id')
            ->get()
            ->map(fn ($account): array => [
                'id' => $account->id,
                'name' => $account->name,
                'type' => $account->type,
                'currency' => $account->currency,
                'balance_role' => $account->balance_role,
                'balance_method' => $account->balance_method,
                'is_active' => $account->is_active,
            ])
            ->all();
    }

    /**
     * @return array{id: int, source_name: string|null, source_label: string, source_metadata: array<string, mixed>|null, original_filename: string, status: string, status_label: string, total_rows: int, imported_rows: int, skipped_rows: int, duplicate_rows: int, error_message: string|null, imported_at: string|null, created_at: string|null, account: array{id: int, name: string, currency: string}|null}
     */
    public function importListItem(Import $import): array
    {
        $statusLabels = $this->statusLabels();
        $sourceLabels = $this->sourceLabels();

        return [
            'id' => $import->id,
            'source_name' => $import->source_name,
            'source_label' => $sourceLabels[$import->source_name] ?? ($import->source_name ?? '-'),
            'source_metadata' => $import->source_metadata,
            'original_filename' => $import->original_filename,
            'status' => $import->status,
            'status_label' => $statusLabels[$import->status] ?? $import->status,
            'total_rows' => $import->total_rows,
            'imported_rows' => $import->imported_rows,
            'skipped_rows' => $import->skipped_rows,
            'duplicate_rows' => $import->duplicate_rows,
            'error_message' => $import->error_message,
            'imported_at' => $import->imported_at?->format('Y-m-d H:i:s'),
            'created_at' => $import->created_at?->format('Y-m-d H:i:s'),
            'account' => $import->account === null ? null : [
                'id' => $import->account->id,
                'name' => $import->account->name,
                'currency' => $import->account->currency,
            ],
        ];
    }
}
