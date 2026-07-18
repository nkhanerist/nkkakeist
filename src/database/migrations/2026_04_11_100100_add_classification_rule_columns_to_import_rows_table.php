<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('import_rows', function (Blueprint $table): void {
            $table->foreignId('matched_classification_rule_id')
                ->nullable()
                ->after('resolved_subcategory_id')
                ->constrained('classification_rules')
                ->nullOnDelete();
            $table->json('rule_applied_fields')
                ->nullable()
                ->after('matched_classification_rule_id');
        });
    }

    public function down(): void
    {
        Schema::table('import_rows', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('matched_classification_rule_id');
            $table->dropColumn('rule_applied_fields');
        });
    }
};
