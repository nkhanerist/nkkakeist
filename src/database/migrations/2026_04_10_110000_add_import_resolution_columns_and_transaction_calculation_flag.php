<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table): void {
            $table->boolean('is_calculation_target')->default(true)->after('is_confirmed');
        });

        Schema::table('import_rows', function (Blueprint $table): void {
            $table->string('account_name')->nullable()->after('description');
            $table->string('category_name')->nullable()->after('account_name');
            $table->string('subcategory_name')->nullable()->after('category_name');
            $table->boolean('is_calculation_target')->nullable()->after('duplicate_hash');
            $table->foreignId('resolved_account_id')->nullable()->after('is_calculation_target')->constrained('accounts')->nullOnDelete();
            $table->foreignId('resolved_category_id')->nullable()->after('resolved_account_id')->constrained('categories')->nullOnDelete();
            $table->foreignId('resolved_subcategory_id')->nullable()->after('resolved_category_id')->constrained('subcategories')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('import_rows', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('resolved_subcategory_id');
            $table->dropConstrainedForeignId('resolved_category_id');
            $table->dropConstrainedForeignId('resolved_account_id');
            $table->dropColumn([
                'account_name',
                'category_name',
                'subcategory_name',
                'is_calculation_target',
            ]);
        });

        Schema::table('transactions', function (Blueprint $table): void {
            $table->dropColumn('is_calculation_target');
        });
    }
};
