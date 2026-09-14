<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class CarrierSettlementLine extends Model
{
    protected $fillable = [
        'carrier_settlement_id', 'shipment_id', 'tracking_number', 'carrier_status',
        'expected_amount', 'paid_amount', 'difference', 'match_status',
        'difference_reason', 'raw_data',
    ];

    protected function casts(): array
    {
        return ['expected_amount' => 'integer', 'paid_amount' => 'integer', 'difference' => 'integer', 'raw_data' => 'array'];
    }

    public function settlement(): BelongsTo
    {
        return $this->belongsTo(CarrierSettlement::class, 'carrier_settlement_id');
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }
}
