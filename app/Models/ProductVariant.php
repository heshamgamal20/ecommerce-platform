<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ProductVariant extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id', 'sku', 'price', 'purchase_price', 'compare_at_price', 'weight', 'status',
        'variant_data', 'combination_hash',
    ];

    protected $hidden = ['combination_hash', 'purchase_price'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function attributeValues(): BelongsToMany
    {
        return $this->belongsToMany(
            AttributeValue::class,
            'product_variant_attribute_values',
            'product_variant_id',
            'attribute_value_id'
        )->withPivot('attribute_id');
    }

    protected function casts(): array
    {
        return [
            'price' => 'integer', 'purchase_price' => 'encrypted:integer',
            'compare_at_price' => 'integer',
            'weight' => 'decimal:3',
            'variant_data' => 'array',
        ];
    }
}
