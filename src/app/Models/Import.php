<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'user_id',
    'account_id',
    'source_name',
    'source_metadata',
    'original_filename',
    'storage_path',
    'status',
    'total_rows',
    'imported_rows',
    'skipped_rows',
    'duplicate_rows',
    'error_message',
    'imported_at',
])]
class Import extends Model
{
    public static function statuses(): array
    {
        return [
            'uploaded',
            'parsed',
            'validated',
            'imported',
            'failed',
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

    public function importRows(): HasMany
    {
        return $this->hasMany(ImportRow::class)
            ->orderBy('row_number');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function accountSnapshots(): HasMany
    {
        return $this->hasMany(AccountSnapshot::class);
    }

    public function investmentPositionSnapshots(): HasMany
    {
        return $this->hasMany(InvestmentPositionSnapshot::class);
    }

    public function assetHistorySnapshots(): HasMany
    {
        return $this->hasMany(AssetHistorySnapshot::class);
    }

    protected function casts(): array
    {
        return [
            'total_rows' => 'integer',
            'imported_rows' => 'integer',
            'skipped_rows' => 'integer',
            'duplicate_rows' => 'integer',
            'imported_at' => 'datetime',
            'source_metadata' => 'array',
        ];
    }
}
