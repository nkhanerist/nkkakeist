<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('investment_position_snapshots', function (Blueprint $table): void {
            $table->decimal('acquisition_cost', 20, 2)->nullable()->after('unit_price');
        });

        DB::table('investment_position_snapshots')
            ->select(['id', 'metadata'])
            ->whereNotNull('metadata')
            ->orderBy('id')
            ->chunkById(100, function ($positions): void {
                foreach ($positions as $position) {
                    $metadata = is_string($position->metadata)
                        ? json_decode($position->metadata, true)
                        : $position->metadata;
                    $acquisitionCost = is_array($metadata)
                        ? ($metadata['acquisition_cost'] ?? null)
                        : null;

                    if (
                        ! is_int($acquisitionCost)
                        && ! is_float($acquisitionCost)
                        && (! is_string($acquisitionCost)
                            || preg_match('/^-?\d+(?:\.\d+)?$/', $acquisitionCost) !== 1)
                    ) {
                        continue;
                    }

                    DB::table('investment_position_snapshots')
                        ->where('id', $position->id)
                        ->update(['acquisition_cost' => (string) $acquisitionCost]);
                }
            });

        DB::table('investment_position_snapshots')
            ->whereNull('acquisition_cost')
            ->whereNotNull('unrealized_gain')
            ->update([
                'acquisition_cost' => DB::raw('valuation - unrealized_gain'),
            ]);
    }

    public function down(): void
    {
        Schema::table('investment_position_snapshots', function (Blueprint $table): void {
            $table->dropColumn('acquisition_cost');
        });
    }
};
