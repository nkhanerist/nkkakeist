<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table): void {
            $table->boolean('affects_account_balance')
                ->default(true)
                ->after('is_calculation_target');
        });

        DB::table('transactions')
            ->where('type', '!=', 'transfer')
            ->update(['affects_account_balance' => DB::raw('is_calculation_target')]);

        Schema::table('import_rows', function (Blueprint $table): void {
            $table->boolean('affects_account_balance')
                ->nullable()
                ->after('is_calculation_target');
            $table->boolean('resolved_affects_account_balance')
                ->nullable()
                ->after('resolved_is_calculation_target');
        });

        Schema::table('imports', function (Blueprint $table): void {
            $table->json('source_metadata')->nullable()->after('source_name');
        });

        Schema::create('account_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('import_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('captured_at');
            $table->decimal('balance', 15, 2);
            $table->string('source_name', 64)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'account_id', 'captured_at']);
            $table->unique('import_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_snapshots');

        Schema::table('imports', function (Blueprint $table): void {
            $table->dropColumn('source_metadata');
        });

        Schema::table('import_rows', function (Blueprint $table): void {
            $table->dropColumn([
                'affects_account_balance',
                'resolved_affects_account_balance',
            ]);
        });

        Schema::table('transactions', function (Blueprint $table): void {
            $table->dropColumn('affects_account_balance');
        });
    }
};
