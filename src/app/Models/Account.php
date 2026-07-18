<?php

namespace App\Models;

use Database\Factories\AccountFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'user_id',
    'name',
    'type',
    'balance_role',
    'balance_method',
    'include_in_net_worth',
    'currency',
    'initial_balance',
    'opening_balance_date',
    'display_order',
    'is_active',
    'note',
    'import_aliases',
])]
class Account extends Model
{
    /** @use HasFactory<AccountFactory> */
    use HasFactory;

    public static function types(): array
    {
        return [
            'cash',
            'bank',
            'credit_card',
            'e_money',
            'securities',
            'point',
            'other',
        ];
    }

    public static function balanceRoles(): array
    {
        return [
            'asset',
            'liability',
            'clearing',
        ];
    }

    public static function balanceMethods(): array
    {
        return [
            'ledger',
            'snapshot',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function incomingTransferTransactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'transfer_account_id');
    }

    public function imports(): HasMany
    {
        return $this->hasMany(Import::class);
    }

    public function snapshots(): HasMany
    {
        return $this->hasMany(AccountSnapshot::class);
    }

    public function investmentPositionSnapshots(): HasMany
    {
        return $this->hasMany(InvestmentPositionSnapshot::class);
    }

    protected function casts(): array
    {
        return [
            'initial_balance' => 'decimal:2',
            'opening_balance_date' => 'date',
            'display_order' => 'integer',
            'is_active' => 'boolean',
            'include_in_net_worth' => 'boolean',
            'import_aliases' => 'array',
        ];
    }

    protected function currency(): Attribute
    {
        return Attribute::make(
            set: static fn (string $value): string => strtoupper($value),
        );
    }
}
