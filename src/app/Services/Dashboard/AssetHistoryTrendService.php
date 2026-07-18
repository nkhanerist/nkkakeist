<?php

namespace App\Services\Dashboard;

use App\Models\AssetHistorySnapshot;
use App\Models\User;

class AssetHistoryTrendService
{
    /**
     * @return array<int, array{currency: string, source_name: string, points: array<int, array{date: string, total_assets: string, breakdown: array<string, string>}>}>
     */
    public function handle(User $user): array
    {
        return AssetHistorySnapshot::query()
            ->where('user_id', $user->id)
            ->orderBy('captured_on')
            ->orderBy('id')
            ->get()
            ->groupBy(fn (AssetHistorySnapshot $snapshot): string => $snapshot->currency.'|'.$snapshot->source_name)
            ->map(function ($snapshots): array {
                /** @var AssetHistorySnapshot $first */
                $first = $snapshots->first();

                return [
                    'currency' => $first->currency,
                    'source_name' => $first->source_name,
                    'points' => $snapshots->map(fn (AssetHistorySnapshot $snapshot): array => [
                        'date' => $snapshot->captured_on->toDateString(),
                        'total_assets' => (string) $snapshot->total_assets,
                        'breakdown' => collect($snapshot->breakdown ?? [])
                            ->map(fn ($value): string => (string) $value)
                            ->all(),
                    ])->values()->all(),
                ];
            })
            ->values()
            ->all();
    }
}
