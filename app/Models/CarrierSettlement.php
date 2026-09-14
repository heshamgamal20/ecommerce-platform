<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class CarrierSettlement extends Model
{
    protected $fillable = [
        'carrier', 'period_start', 'period_end', 'currency', 'gross_cod_amount',
        'shipping_fees', 'return_fees', 'other_adjustments', 'expected_amount',
        'paid_amount', 'difference', 'status', 'provider_reference', 'notes',
        'metadata', 'created_by', 'statement_file_name', 'approved_at', 'approved_by', 'locked_at', 'locked_by',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date', 'period_end' => 'date',
            'gross_cod_amount' => 'integer', 'shipping_fees' => 'integer',
            'return_fees' => 'integer', 'other_adjustments' => 'integer',
            'expected_amount' => 'integer', 'paid_amount' => 'integer',
            'difference' => 'integer', 'metadata' => 'array', 'approved_at' => 'datetime', 'locked_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(CarrierSettlementLine::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function locker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'locked_by');
    }
}
