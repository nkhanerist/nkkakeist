<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            DB::table('investment_position_snapshots')
                ->where(function ($query): void {
                    $query->where('asset_class', 'defined_contribution_pension')
                        ->orWhere('external_id', 'like', 'money_forward:pension:%');
                })
                ->orderBy('id')
                ->chunkById(100, function ($positions): void {
                    foreach ($positions as $position) {
                        $normalizedName = Str::lower(Str::squish(
                            mb_convert_kana($position->instrument_name, 'asKV', 'UTF-8'),
                        ));
                        $canonicalKey = hash('sha256', implode('|', [
                            'money_forward:pension:'.$normalizedName,
                            strtoupper($position->currency),
                        ]));

                        if ($position->position_key === $canonicalKey) {
                            continue;
                        }

                        $canonicalPositionExists = DB::table('investment_position_snapshots')
                            ->where('account_snapshot_id', $position->account_snapshot_id)
                            ->where('position_key', $canonicalKey)
                            ->where('id', '!=', $position->id)
                            ->exists();

                        if ($canonicalPositionExists) {
                            continue;
                        }

                        DB::table('investment_position_snapshots')
                            ->where('id', $position->id)
                            ->update(['position_key' => $canonicalKey]);
                    }
                });
        });
    }

    public function down(): void
    {
        // Canonical position keys cannot be mapped back to their former external-ID variants.
    }
};
