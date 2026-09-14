<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
final class Invoice extends Model
{
    protected $fillable = ['order_id','number','status','type','currency','subtotal_amount','tax_amount','total_amount','issued_at','cancelled_at'];
    protected function casts(): array { return ['issued_at'=>'datetime','cancelled_at'=>'datetime','subtotal_amount'=>'integer','tax_amount'=>'integer','total_amount'=>'integer']; }
    public function order(): BelongsTo { return $this->belongsTo(CustomerOrder::class, 'order_id'); }
    public function items(): HasMany { return $this->hasMany(InvoiceItem::class); }
    public function creditNotes(): HasMany { return $this->hasMany(CreditNote::class); }
}
