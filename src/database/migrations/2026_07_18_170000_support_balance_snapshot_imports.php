<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('import_rows', function (Blueprint $table): void {
            $table->foreignId('manual_resolved_account_id')
                ->nullable()
                ->after('resolved_account_id')
                ->constrained('accounts')
                ->nullOnDelete();
        });

        Schema::table('account_snapshots', function (Blueprint $table): void {
            $table->index('import_id');
            $table->dropUnique(['import_id']);
            $table->foreignId('import_row_id')
                ->nullable()
                ->after('import_id')
                ->constrained('import_rows')
                ->nullOnDelete();
            $table->string('duplicate_hash', 64)
                ->nullable()
                ->after('purpose');

            $table->unique('import_row_id');
            $table->index(['user_id', 'duplicate_hash']);
        });
    }

    public function down(): void
    {
        Schema::table('account_snapshots', function (Blueprint $table): void {
            $table->dropIndex(['user_id', 'duplicate_hash']);
            $table->dropUnique(['import_row_id']);
            $table->dropConstrainedForeignId('import_row_id');
            $table->dropColumn('duplicate_hash');
            $table->unique('import_id');
            $table->dropIndex(['import_id']);
        });

        Schema::table('import_rows', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('manual_resolved_account_id');
        });
    }
};
