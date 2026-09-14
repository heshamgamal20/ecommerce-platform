<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
final class OrderReturn extends Model
{
    protected $table = 'order_returns';
    protected $fillable = ['order_id','payment_id','user_id','status','reason','notes','refund_amount','rejection_reason','received_at','received_by','inspection_status','inspection_notes','inspected_at','inspected_by','final_refund_amount','refunded_at'];
    protected function casts(): array { return ['refund_amount' => 'integer', 'final_refund_amount' => 'integer', 'received_at' => 'datetime', 'inspected_at' => 'datetime', 'refunded_at' => 'datetime']; }
    public function order(): BelongsTo { return $this->belongsTo(CustomerOrder::class, 'order_id'); }
    public function payment(): BelongsTo { return $this->belongsTo(Payment::class, 'payment_id'); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function receiver(): BelongsTo { return $this->belongsTo(User::class, 'received_by'); }
    public function inspector(): BelongsTo { return $this->belongsTo(User::class, 'inspected_by'); }
    public function items(): HasMany { return $this->hasMany(OrderReturnItem::class, 'return_id'); }
}
