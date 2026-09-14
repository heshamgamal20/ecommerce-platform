<?php

namespace App\Modules\Catalog\Domain\ValueObjects;

final readonly class ProductVariantData
{
    public function __construct(
        public string $sku,
        public int $price,
        public ?int $purchasePrice,
        public ?int $compareAtPrice,
        public ?float $weight,
        public string $status,
        public ?array $variantData,
        public array $attributeValueIds,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            $data['sku'], (int) $data['price'], isset($data['purchase_price']) ? (int) $data['purchase_price'] : null,
            isset($data['compare_at_price']) ? (int) $data['compare_at_price'] : null,
            isset($data['weight']) ? (float) $data['weight'] : null,
            $data['status'], $data['variant_data'] ?? null,
            array_values(array_map('intval', $data['attribute_value_ids'])),
        );
    }

    public function persistenceData(): array
    {
        return [
            'sku' => $this->sku, 'price' => $this->price, 'purchase_price' => $this->purchasePrice,
            'compare_at_price' => $this->compareAtPrice, 'weight' => $this->weight,
            'status' => $this->status, 'variant_data' => $this->variantData,
        ];
    }
}
