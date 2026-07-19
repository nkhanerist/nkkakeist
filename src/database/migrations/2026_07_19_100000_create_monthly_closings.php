<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounts', function (Blueprint $table): void {
            $table->boolean('monthly_close_required')
                ->default(false)
                ->after('include_in_net_worth');
        });

        DB::table('accounts')
            ->where('is_active', true)
            ->whereIn('type', ['bank', 'credit_card', 'e_money', 'securities', 'point'])
            ->update(['monthly_close_required' => true]);

        Schema::create('monthly_closings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('month');
            $table->string('status', 32)->default('open');
            $table->text('note')->nullable();
            $table->string('data_fingerprint', 64)->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamp('last_reopened_at')->nullable();
            $table->text('last_reopen_reason')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'month']);
            $table->index(['user_id', 'status']);
        });

        Schema::create('monthly_close_account_confirmations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('monthly_closing_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->string('data_fingerprint', 64);
            $table->timestamp('confirmed_at');
            $table->timestamps();

            $table->unique(['monthly_closing_id', 'account_id'], 'monthly_close_account_unique');
            $table->index(['user_id', 'account_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monthly_close_account_confirmations');
        Schema::dropIfExists('monthly_closings');

        Schema::table('accounts', function (Blueprint $table): void {
            $table->dropColumn('monthly_close_required');
        });
    }
};
