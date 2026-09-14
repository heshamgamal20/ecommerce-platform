<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
final class CreditNote extends Model
{
    protected $fillable = ['invoice_id','return_id','number','status','reason','currency','amount','issued_at'];
    protected function casts(): array { return ['amount'=>'integer','issued_at'=>'datetime']; }
    public function invoice(): BelongsTo { return $this->belongsTo(Invoice::class); }
    public function return(): BelongsTo { return $this->belongsTo(OrderReturn::class, 'return_id'); }
}
