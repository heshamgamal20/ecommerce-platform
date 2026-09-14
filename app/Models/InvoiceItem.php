<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
final class InvoiceItem extends Model
{
    protected $fillable = ['invoice_id','order_item_id','name','quantity','unit_price','tax_amount','total_amount'];
    protected function casts(): array { return ['quantity'=>'integer','unit_price'=>'integer','tax_amount'=>'integer','total_amount'=>'integer']; }
    public function invoice(): BelongsTo { return $this->belongsTo(Invoice::class); }
}
