<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'import_id',
    'import_row_id',
    'captured_on',
    'total_assets',
    'currency',
    'source_name',
    'duplicate_hash',
    'breakdown',
])]
class AssetHistorySnapshot extends Model
{
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
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
            'captured_on' => 'date',
            'total_assets' => 'decimal:2',
            'breakdown' => 'array',
        ];
    }
}
