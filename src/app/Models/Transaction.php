<?php

namespace App\Models;

use Database\Factories\TransactionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'user_id',
    'account_id',
    'transfer_account_id',
    'transaction_date',
    'posted_at',
    'type',
    'amount',
    'currency',
    'merchant_name',
    'description',
    'category_id',
    'subcategory_id',
    'payment_method_label',
    'external_id',
    'import_id',
    'import_row_id',
    'duplicate_hash',
    'is_confirmed',
    'is_calculation_target',
    'affects_account_balance',
    'memo',
])]
class Transaction extends Model
{
    /** @use HasFactory<TransactionFactory> */
    use HasFactory, SoftDeletes;

    public static function types(): array
    {
        return [
            'income',
            'expense',
            'transfer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function transferAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'transfer_account_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function subcategory(): BelongsTo
    {
        return $this->belongsTo(Subcategory::class);
    }

    public function import(): BelongsTo
    {
        return $this->belongsTo(Import::class);
    }

    public function importRow(): BelongsTo
    {
        return $this->belongsTo(ImportRow::class);
    }

    protected function casts(): array
    {
        return [
            'transaction_date' => 'date',
            'posted_at' => 'datetime',
            'amount' => 'decimal:2',
            'is_confirmed' => 'boolean',
            'is_calculation_target' => 'boolean',
            'affects_account_balance' => 'boolean',
        ];
    }

    protected function currency(): Attribute
    {
        return Attribute::make(
            set: static fn (string $value): string => strtoupper($value),
        );
    }
}
