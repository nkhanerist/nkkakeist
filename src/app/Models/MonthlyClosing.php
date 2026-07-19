<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'user_id',
    'month',
    'status',
    'note',
    'data_fingerprint',
    'reviewed_at',
    'closed_at',
    'last_reopened_at',
    'last_reopen_reason',
])]
class MonthlyClosing extends Model
{
    public static function statuses(): array
    {
        return ['open', 'reviewed', 'closed'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function accountConfirmations(): HasMany
    {
        return $this->hasMany(MonthlyCloseAccountConfirmation::class);
    }

    protected function casts(): array
    {
        return [
            'month' => 'date',
            'reviewed_at' => 'datetime',
            'closed_at' => 'datetime',
            'last_reopened_at' => 'datetime',
        ];
    }
}
