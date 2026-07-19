<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'monthly_closing_id',
    'account_id',
    'data_fingerprint',
    'confirmed_at',
])]
class MonthlyCloseAccountConfirmation extends Model
{
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function monthlyClosing(): BelongsTo
    {
        return $this->belongsTo(MonthlyClosing::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    protected function casts(): array
    {
        return [
            'confirmed_at' => 'datetime',
        ];
    }
}
