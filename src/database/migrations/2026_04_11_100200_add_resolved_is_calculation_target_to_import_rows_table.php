<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('import_rows', function (Blueprint $table): void {
            $table->boolean('resolved_is_calculation_target')
                ->nullable()
                ->after('is_calculation_target');
        });

        DB::table('import_rows')->update([
            'resolved_is_calculation_target' => DB::raw('is_calculation_target'),
        ]);
    }

    public function down(): void
    {
        Schema::table('import_rows', function (Blueprint $table): void {
            $table->dropColumn('resolved_is_calculation_target');
        });
    }
};
