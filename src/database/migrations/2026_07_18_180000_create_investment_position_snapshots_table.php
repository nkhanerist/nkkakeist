<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('investment_position_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_snapshot_id')->constrained()->cascadeOnDelete();
            $table->foreignId('import_id')->constrained()->cascadeOnDelete();
            $table->timestamp('captured_at');
            $table->string('position_key', 64);
            $table->string('instrument_name');
            $table->string('instrument_code', 64)->nullable();
            $table->string('external_id')->nullable();
            $table->string('asset_class', 64)->nullable();
            $table->decimal('quantity', 24, 8)->nullable();
            $table->decimal('average_acquisition_price', 20, 6)->nullable();
            $table->decimal('unit_price', 20, 6)->nullable();
            $table->decimal('valuation', 15, 2);
            $table->decimal('unrealized_gain', 15, 2)->nullable();
            $table->char('currency', 3);
            $table->string('source_name', 64)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['account_snapshot_id', 'position_key'], 'investment_positions_snapshot_key_unique');
            $table->index(
                ['user_id', 'account_id', 'captured_at'],
                'investment_positions_account_date_index',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('investment_position_snapshots');
    }
};
