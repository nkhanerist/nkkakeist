<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_history_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('import_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('import_row_id')->nullable()->constrained()->nullOnDelete();
            $table->date('captured_on');
            $table->decimal('total_assets', 15, 2);
            $table->string('currency', 3)->default('JPY');
            $table->string('source_name', 64);
            $table->string('duplicate_hash', 64);
            $table->json('breakdown');
            $table->timestamps();

            $table->unique(['user_id', 'captured_on', 'source_name']);
            $table->unique('import_row_id');
            $table->index(['user_id', 'currency', 'captured_on']);
            $table->index('duplicate_hash');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_history_snapshots');
    }
};
