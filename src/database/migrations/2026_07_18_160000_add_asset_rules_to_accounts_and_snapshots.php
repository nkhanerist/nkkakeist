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
            $table->string('balance_role', 32)->default('asset')->after('type');
            $table->string('balance_method', 32)->default('ledger')->after('balance_role');
            $table->boolean('include_in_net_worth')->default(true)->after('balance_method');
            $table->date('opening_balance_date')->nullable()->after('initial_balance');

            $table->index(['user_id', 'include_in_net_worth', 'balance_role'], 'accounts_net_worth_role_index');
        });

        DB::table('accounts')
            ->where('type', 'credit_card')
            ->update(['balance_role' => 'liability']);

        DB::table('accounts')
            ->where('type', 'other')
            ->update([
                'balance_role' => 'clearing',
                'include_in_net_worth' => false,
            ]);

        DB::table('accounts')
            ->where('type', 'securities')
            ->update(['balance_method' => 'snapshot']);

        DB::table('accounts')
            ->whereIn('name', ['d払い', 'Amazon.co.jp'])
            ->update([
                'balance_role' => 'clearing',
                'include_in_net_worth' => false,
            ]);

        DB::table('accounts')
            ->whereRaw('LOWER(name) = ?', ['kyash'])
            ->update([
                'balance_role' => 'asset',
                'balance_method' => 'ledger',
                'include_in_net_worth' => true,
            ]);

        DB::table('accounts')
            ->where('name', 'テスト口座')
            ->update(['include_in_net_worth' => false]);

        Schema::table('account_snapshots', function (Blueprint $table): void {
            $table->string('purpose', 32)->default('reconciliation')->after('captured_at');
            $table->text('note')->nullable()->after('source_name');

            $table->index(
                ['user_id', 'account_id', 'purpose', 'captured_at'],
                'account_snapshots_purpose_date_index',
            );
        });
    }

    public function down(): void
    {
        Schema::table('account_snapshots', function (Blueprint $table): void {
            $table->dropIndex('account_snapshots_purpose_date_index');
            $table->dropColumn(['purpose', 'note']);
        });

        Schema::table('accounts', function (Blueprint $table): void {
            $table->dropIndex('accounts_net_worth_role_index');
            $table->dropColumn([
                'balance_role',
                'balance_method',
                'include_in_net_worth',
                'opening_balance_date',
            ]);
        });
    }
};
