<?php

namespace App\Modules\Shipping\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\CarrierSettlement;
use App\Models\Shipment;
use App\Modules\Shipping\Domain\ValueObjects\CreateShipmentData;
use App\Modules\Shipping\Application\UseCases\CreateShipment;
use App\Modules\Shipping\Application\UseCases\CreateShippingMethod;
use App\Modules\Shipping\Application\UseCases\DeleteShippingMethod;
use App\Modules\Shipping\Application\UseCases\GetShippingMethod;
use App\Modules\Shipping\Application\UseCases\ListAllShippingMethods;
use App\Modules\Shipping\Application\UseCases\ListOrderShipments;
use App\Modules\Shipping\Application\UseCases\ListShippingMethods;
use App\Modules\Shipping\Application\UseCases\UpdateShipmentStatus;
use App\Modules\Shipping\Application\UseCases\UpdateShippingMethod;
use App\Modules\Shipping\Presentation\Http\Requests\ShippingRequest;
use App\Modules\Shipping\Presentation\Http\Requests\ShippingSettlementRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class ShippingController extends Controller
{
    public function report(ShippingSettlementRequest $request): JsonResponse
    {
        $data = $request->validated();
        $rows = $this->carrierReport($data['from'], $data['to'], $data['carrier'] ?? null);

        return response()->json(['data' => [
            'from' => $data['from'], 'to' => $data['to'], 'carriers' => $rows->values(),
            'totals' => [
                'shipments' => $rows->sum('shipments'), 'delivered' => $rows->sum('delivered'),
                'returned' => $rows->sum('returned'), 'cancelled' => $rows->sum('cancelled'),
                'shipping_fees' => $rows->sum('shipping_fees'), 'gross_cod_amount' => $rows->sum('gross_cod_amount'),
                'expected_amount' => $rows->sum('expected_amount'), 'currency' => $rows->pluck('currency')->filter()->unique()->values(),
            ],
        ]]);
    }

    public function settlements(ShippingSettlementRequest $request): JsonResponse
    {
        $settlement = DB::transaction(function () use ($request): CarrierSettlement {
            $data = $request->validated();
            $summary = $this->carrierReport($data['period_start'], $data['period_end'], $data['carrier'])->first()
                ?? $this->emptyCarrierReport($data['carrier'], $data['currency'] ?? 'EGP');
            $adjustments = (int) ($data['other_adjustments'] ?? 0);
            $expected = (int) $summary['expected_amount'] + $adjustments;
            $paid = (int) $data['paid_amount'];

            return CarrierSettlement::query()->create([
                'carrier' => $data['carrier'], 'period_start' => $data['period_start'], 'period_end' => $data['period_end'],
                'currency' => $data['currency'] ?? $summary['currency'], 'gross_cod_amount' => $summary['gross_cod_amount'],
                'shipping_fees' => $summary['shipping_fees'], 'return_fees' => $summary['return_fees'],
                'other_adjustments' => $adjustments, 'expected_amount' => $expected, 'paid_amount' => $paid,
                'difference' => $paid - $expected, 'status' => $paid === $expected ? 'settled' : 'disputed',
                'provider_reference' => $data['provider_reference'] ?? null, 'notes' => $data['notes'] ?? null,
                'metadata' => ['report_snapshot' => $summary], 'created_by' => $request->user()?->id,
            ]);
        });

        return response()->json(['data' => $settlement], 201);
    }

    public function settlementIndex(ShippingSettlementRequest $request): JsonResponse
    {
        $data = $request->validated();
        $settlements = CarrierSettlement::query()->latest('period_end')
            ->when($data['carrier'] ?? null, fn ($query, $value) => $query->where('carrier', $value))
            ->when($data['status'] ?? null, fn ($query, $value) => $query->where('status', $value))
            ->paginate(min(max((int) $request->integer('per_page', 50), 1), 100));

        return response()->json(['data' => $settlements]);
    }

    private function carrierReport(string $from, string $to, ?string $carrier): Collection
    {
        $shipments = Shipment::query()->with(['method', 'order.payments'])
            ->whereBetween('created_at', [$from.' 00:00:00', $to.' 23:59:59'])
            ->when($carrier !== null, fn ($query) => $query->whereHas('method', fn ($method) => $method->where('carrier', $carrier)))
            ->get();
        $rows = [];
        foreach ($shipments as $shipment) {
            $name = (string) ($shipment->method?->carrier ?: $shipment->method_code);
            $row = $rows[$name] ?? $this->emptyCarrierReport($name, (string) $shipment->currency);
            $row['shipments']++;
            $row[$shipment->status] = ($row[$shipment->status] ?? 0) + 1;
            $row['shipping_fees'] += (int) $shipment->fee;
            if ($shipment->status === 'cancelled') $row['return_fees'] += (int) $shipment->fee;
            $isCod = $shipment->order?->payments?->contains(fn ($payment) => $payment->method === 'cash_on_delivery');
            if ($shipment->status === 'delivered' && $isCod) $row['gross_cod_amount'] += (int) $shipment->order->total_amount;
            $row['expected_amount'] = $row['gross_cod_amount'] - $row['shipping_fees'] - $row['return_fees'];
            $rows[$name] = $row;
        }
        return collect($rows)->map(fn (array $row) => collect($row)->only([
            'carrier', 'currency', 'shipments', 'pending', 'processing', 'provider_created', 'picked_up',
            'in_transit', 'out_for_delivery', 'delivered', 'cancelled', 'failed', 'returned',
            'shipping_fees', 'return_fees', 'gross_cod_amount', 'expected_amount',
        ])->all());
    }

    private function emptyCarrierReport(string $carrier, string $currency): array
    {
        return ['carrier' => $carrier, 'currency' => $currency, 'shipments' => 0, 'shipping_fees' => 0,
            'return_fees' => 0, 'gross_cod_amount' => 0, 'expected_amount' => 0];
    }
    public function customerMethods(ShippingRequest $request, ListShippingMethods $methods): JsonResponse { return response()->json(['data' => $methods->execute()]); }
    public function customerShipments(ShippingRequest $request, int $orderId, ListOrderShipments $shipments): JsonResponse { return response()->json(['data' => $shipments->execute($orderId)]); }
    public function createShipment(ShippingRequest $request, int $orderId, CreateShipment $create): JsonResponse
    {
        $data = $request->validated();
        return response()->json(['data' => $create->execute($orderId, new CreateShipmentData((int) $data['shipping_method_id'], $data['idempotency_key']))], 201);
    }
    public function index(ShippingRequest $request, ListAllShippingMethods $methods): JsonResponse { return response()->json(['data' => $methods->execute()]); }
    public function show(ShippingRequest $request, int $id, GetShippingMethod $method): JsonResponse { return response()->json(['data' => $method->execute($id)]); }
    public function store(ShippingRequest $request, CreateShippingMethod $create): JsonResponse { return response()->json(['data' => $create->execute($request->validated())], 201); }
    public function update(ShippingRequest $request, int $id, UpdateShippingMethod $update): JsonResponse { return response()->json(['data' => $update->execute($id, $request->validated())]); }
    public function destroy(ShippingRequest $request, int $id, DeleteShippingMethod $delete): JsonResponse { $delete->execute($id); return response()->json(null, 204); }
    public function status(ShippingRequest $request, int $id, UpdateShipmentStatus $update): JsonResponse
    {
        $data = $request->validated();
        return response()->json(['data' => $update->execute($id, $data['status'], $data['note'] ?? null)]);
    }
}
