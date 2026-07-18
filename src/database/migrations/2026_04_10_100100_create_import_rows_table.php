<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_rows', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('import_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('row_number');
            $table->json('raw_payload');
            $table->date('transaction_date')->nullable();
            $table->decimal('amount', 14, 2)->nullable();
            $table->string('merchant_name')->nullable();
            $table->text('description')->nullable();
            $table->string('detected_type', 32)->nullable();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('subcategory_id')->nullable()->constrained()->nullOnDelete();
            $table->string('duplicate_hash')->nullable();
            $table->boolean('is_duplicate_candidate')->default(false);
            $table->json('validation_errors')->nullable();
            $table->string('status', 32);
            $table->timestamps();

            $table->index(['import_id', 'row_number']);
            $table->index(['import_id', 'status']);
            $table->index('duplicate_hash');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_rows');
    }
};
