<?php

namespace App\Modules\Inventory\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use App\Modules\Inventory\Presentation\Http\Requests\AdminInventoryRequest;
use Illuminate\Http\JsonResponse;

final class AdminInventoryController extends Controller
{
    public function index(AdminInventoryRequest $request): JsonResponse
    {
        $filters = $request->validated();
        $threshold = (int) ($filters['threshold'] ?? 5);
        $items = InventoryItem::query()->with(['product:id,name,slug,status', 'variant:id,product_id,sku,price'])
            ->when($filters['q'] ?? null, fn ($query, $q) => $query->where(fn ($inner) => $inner->whereHas('product', fn ($product) => $product->where('name', 'like', '%'.$q.'%')->orWhere('slug', 'like', '%'.$q.'%'))->orWhereHas('variant', fn ($variant) => $variant->where('sku', 'like', '%'.$q.'%'))))
            ->when($filters['product_id'] ?? null, fn ($query, $id) => $query->where('product_id', $id))
            ->when($filters['variant_id'] ?? null, fn ($query, $id) => $query->where('variant_id', $id))
            ->when($filters['low_stock'] ?? false, fn ($query) => $query->whereRaw('(on_hand - reserved) <= ?', [$threshold]))
            ->latest('id')->paginate(min(max((int) ($filters['per_page'] ?? 25), 1), 100));
        return response()->json(['data' => $items, 'filters' => ['threshold' => $threshold]]);
    }

    public function show(AdminInventoryRequest $request, int $item): JsonResponse
    {
        $inventory = InventoryItem::query()->with(['product', 'variant', 'movements.actor:id,name,email'])->findOrFail($item);
        return response()->json(['data' => $inventory]);
    }

    public function movements(AdminInventoryRequest $request, int $item): JsonResponse
    {
        $filters = $request->validated();
        InventoryItem::query()->findOrFail($item);
        $movements = InventoryMovement::query()->with('actor:id,name,email')->where('inventory_item_id', $item)
            ->when($filters['reason'] ?? null, fn ($query, $reason) => $query->where('reason', $reason))
            ->when($filters['from'] ?? null, fn ($query, $from) => $query->whereDate('created_at', '>=', $from))
            ->when($filters['to'] ?? null, fn ($query, $to) => $query->whereDate('created_at', '<=', $to))
            ->latest('id')->paginate(min(max((int) ($filters['per_page'] ?? 50), 1), 100));
        return response()->json(['data' => $movements]);
    }
}
