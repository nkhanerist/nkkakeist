<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'account_id',
    'account_snapshot_id',
    'import_id',
    'captured_at',
    'position_key',
    'instrument_name',
    'instrument_code',
    'external_id',
    'asset_class',
    'quantity',
    'average_acquisition_price',
    'unit_price',
    'acquisition_cost',
    'valuation',
    'unrealized_gain',
    'currency',
    'source_name',
    'metadata',
])]
class InvestmentPositionSnapshot extends Model
{
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function accountSnapshot(): BelongsTo
    {
        return $this->belongsTo(AccountSnapshot::class);
    }

    public function import(): BelongsTo
    {
        return $this->belongsTo(Import::class);
    }

    protected function casts(): array
    {
        return [
            'captured_at' => 'datetime',
            'quantity' => 'decimal:8',
            'average_acquisition_price' => 'decimal:6',
            'unit_price' => 'decimal:6',
            'acquisition_cost' => 'decimal:2',
            'valuation' => 'decimal:2',
            'unrealized_gain' => 'decimal:2',
            'metadata' => 'array',
        ];
    }
}
