<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'account_id',
    'import_id',
    'import_row_id',
    'captured_at',
    'purpose',
    'duplicate_hash',
    'balance',
    'source_name',
    'note',
    'metadata',
])]
class AccountSnapshot extends Model
{
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
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
            'captured_at' => 'datetime',
            'balance' => 'decimal:2',
            'metadata' => 'array',
        ];
    }
}
