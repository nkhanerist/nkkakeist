<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->constrained()->restrictOnDelete();
            $table->foreignId('transfer_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->date('transaction_date');
            $table->timestamp('posted_at')->nullable();
            $table->string('type', 32);
            $table->decimal('amount', 15, 2);
            $table->string('currency', 3)->default('JPY');
            $table->string('merchant_name')->nullable();
            $table->text('description')->nullable();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('subcategory_id')->nullable()->constrained()->nullOnDelete();
            $table->string('payment_method_label')->nullable();
            $table->string('external_id')->nullable();
            $table->unsignedBigInteger('import_id')->nullable();
            $table->unsignedBigInteger('import_row_id')->nullable();
            $table->string('duplicate_hash')->nullable();
            $table->boolean('is_confirmed')->default(true);
            $table->text('memo')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'transaction_date']);
            $table->index(['user_id', 'account_id', 'transaction_date']);
            $table->index(['user_id', 'category_id', 'transaction_date']);
            $table->index(['user_id', 'duplicate_hash']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
