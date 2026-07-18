<?php

namespace App\Services\Dashboard;

use App\Models\Import;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

class WeeklyImportStatusService
{
    /**
     * @return array{
     *     week_start: string,
     *     week_end: string,
     *     sources: array{
     *         jre_point: array{state: 'updated'|'stale'|'missing', last_updated_at: string|null, latest_history_date: string|null},
     *         mobile_suica: array{state: 'updated'|'stale'|'missing', last_updated_at: string|null, latest_history_date: string|null}
     *     }
     * }
     */
    public function handle(User $user): array
    {
        $now = CarbonImmutable::now(config('app.timezone'));
        $weekStart = $now->startOfWeek(CarbonInterface::MONDAY)->startOfDay();
        $weekEnd = $now->endOfWeek(CarbonInterface::SUNDAY)->endOfDay();

        $imports = Import::query()
            ->where('user_id', $user->id)
            ->whereIn('source_name', ['jre_point', 'mobile_suica'])
            ->where('status', 'imported')
            ->withMax('importRows', 'transaction_date')
            ->latest('created_at')
            ->get()
            ->unique('source_name')
            ->keyBy('source_name');

        $jrePointImport = $imports->get('jre_point');
        $mobileSuicaImport = $imports->get('mobile_suica');

        return [
            'week_start' => $weekStart->toDateString(),
            'week_end' => $weekEnd->toDateString(),
            'sources' => [
                'jre_point' => $this->sourceStatus(
                    $jrePointImport,
                    $this->jrePointCapturedAt($jrePointImport),
                    $weekStart,
                    $weekEnd,
                ),
                'mobile_suica' => $this->sourceStatus(
                    $mobileSuicaImport,
                    $this->importedAt($mobileSuicaImport),
                    $weekStart,
                    $weekEnd,
                ),
            ],
        ];
    }

    /**
     * @return array{state: 'updated'|'stale'|'missing', last_updated_at: string|null, latest_history_date: string|null}
     */
    private function sourceStatus(
        ?Import $import,
        ?CarbonImmutable $updatedAt,
        CarbonImmutable $weekStart,
        CarbonImmutable $weekEnd,
    ): array {
        $state = match (true) {
            $import === null || $updatedAt === null => 'missing',
            $updatedAt->betweenIncluded($weekStart, $weekEnd) => 'updated',
            default => 'stale',
        };

        $latestHistoryDate = is_string($import?->import_rows_max_transaction_date)
            ? CarbonImmutable::parse($import->import_rows_max_transaction_date)->toDateString()
            : null;

        return [
            'state' => $state,
            'last_updated_at' => $updatedAt?->toIso8601String(),
            'latest_history_date' => $latestHistoryDate,
        ];
    }

    private function jrePointCapturedAt(?Import $import): ?CarbonImmutable
    {
        $capturedAt = $import?->source_metadata['captured_at'] ?? null;

        if (is_string($capturedAt)) {
            return CarbonImmutable::parse($capturedAt)->setTimezone(config('app.timezone'));
        }

        return $this->importedAt($import);
    }

    private function importedAt(?Import $import): ?CarbonImmutable
    {
        $value = $import?->imported_at ?? $import?->created_at;

        return $value === null
            ? null
            : CarbonImmutable::instance($value)->setTimezone(config('app.timezone'));
    }
}
