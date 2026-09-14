<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class CouponUsage extends Model
{
    protected $fillable = ['coupon_id', 'user_id', 'order_id', 'discount_amount'];
    protected function casts(): array { return ['discount_amount' => 'integer']; }
    public function coupon(): BelongsTo { return $this->belongsTo(Coupon::class); }
}
