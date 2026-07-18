<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'user_id',
    'name',
    'transaction_type',
    'match_field',
    'match_operator',
    'match_value',
    'category_id',
    'subcategory_id',
    'is_calculation_target',
    'priority',
    'is_active',
])]
class ClassificationRule extends Model
{
    public static function transactionTypes(): array
    {
        return [
            'income',
            'expense',
            'transfer',
            'any',
        ];
    }

    public static function applicableTransactionTypes(): array
    {
        return [
            'income',
            'expense',
            'any',
        ];
    }

    public static function matchFields(): array
    {
        return [
            'merchant_name',
            'description',
            'account_name',
        ];
    }

    public static function matchOperators(): array
    {
        return [
            'contains',
            'equals',
            'starts_with',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function subcategory(): BelongsTo
    {
        return $this->belongsTo(Subcategory::class);
    }

    public function matchedImportRows(): HasMany
    {
        return $this->hasMany(ImportRow::class, 'matched_classification_rule_id');
    }

    protected function casts(): array
    {
        return [
            'is_calculation_target' => 'boolean',
            'priority' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
