<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'import_id',
    'row_number',
    'raw_payload',
    'transaction_date',
    'amount',
    'merchant_name',
    'description',
    'account_name',
    'category_name',
    'subcategory_name',
    'detected_type',
    'category_id',
    'subcategory_id',
    'duplicate_hash',
    'resolved_account_id',
    'manual_resolved_account_id',
    'replace_account_snapshot_id',
    'resolved_transfer_account_id',
    'manual_resolved_transfer_account_id',
    'resolved_category_id',
    'resolved_subcategory_id',
    'matched_classification_rule_id',
    'rule_applied_fields',
    'is_calculation_target',
    'affects_account_balance',
    'resolved_is_calculation_target',
    'resolved_affects_account_balance',
    'is_duplicate_candidate',
    'validation_errors',
    'transfer_resolution',
    'status',
])]
class ImportRow extends Model
{
    public static function statuses(): array
    {
        return [
            'pending',
            'ready',
            'imported',
            'skipped',
            'error',
        ];
    }

    public static function detectedTypes(): array
    {
        return [
            'income',
            'expense',
            'transfer',
            'unknown',
        ];
    }

    public function import(): BelongsTo
    {
        return $this->belongsTo(Import::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function subcategory(): BelongsTo
    {
        return $this->belongsTo(Subcategory::class);
    }

    public function resolvedAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'resolved_account_id');
    }

    public function manualResolvedAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'manual_resolved_account_id');
    }

    public function replacementAccountSnapshot(): BelongsTo
    {
        return $this->belongsTo(AccountSnapshot::class, 'replace_account_snapshot_id');
    }

    public function resolvedTransferAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'resolved_transfer_account_id');
    }

    public function manualResolvedTransferAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'manual_resolved_transfer_account_id');
    }

    public function resolvedCategory(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'resolved_category_id');
    }

    public function resolvedSubcategory(): BelongsTo
    {
        return $this->belongsTo(Subcategory::class, 'resolved_subcategory_id');
    }

    public function matchedClassificationRule(): BelongsTo
    {
        return $this->belongsTo(ClassificationRule::class, 'matched_classification_rule_id');
    }

    public function transaction(): HasOne
    {
        return $this->hasOne(Transaction::class, 'import_row_id');
    }

    protected function casts(): array
    {
        return [
            'raw_payload' => 'array',
            'transaction_date' => 'date',
            'amount' => 'decimal:2',
            'resolved_account_id' => 'integer',
            'manual_resolved_account_id' => 'integer',
            'replace_account_snapshot_id' => 'integer',
            'resolved_transfer_account_id' => 'integer',
            'manual_resolved_transfer_account_id' => 'integer',
            'resolved_category_id' => 'integer',
            'resolved_subcategory_id' => 'integer',
            'matched_classification_rule_id' => 'integer',
            'is_duplicate_candidate' => 'boolean',
            'is_calculation_target' => 'boolean',
            'affects_account_balance' => 'boolean',
            'resolved_is_calculation_target' => 'boolean',
            'resolved_affects_account_balance' => 'boolean',
            'rule_applied_fields' => 'array',
            'validation_errors' => 'array',
            'transfer_resolution' => 'array',
            'row_number' => 'integer',
        ];
    }
}
