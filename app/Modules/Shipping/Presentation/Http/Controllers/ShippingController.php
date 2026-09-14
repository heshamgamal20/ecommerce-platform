<?php

namespace App\Modules\Shipping\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\CarrierSettlement;
use App\Models\CarrierSettlementLine;
use App\Models\Shipment;
use App\Modules\Shipping\Application\UseCases\CreateShipment;
use App\Modules\Shipping\Application\UseCases\CreateShippingMethod;
use App\Modules\Shipping\Application\UseCases\DeleteShippingMethod;
use App\Modules\Shipping\Application\UseCases\GetShippingMethod;
use App\Modules\Shipping\Application\UseCases\ListAllShippingMethods;
use App\Modules\Shipping\Application\UseCases\ListOrderShipments;
use App\Modules\Shipping\Application\UseCases\ListShippingMethods;
use App\Modules\Shipping\Application\UseCases\UpdateShipmentStatus;
use App\Modules\Shipping\Application\UseCases\UpdateShippingMethod;
use App\Modules\Shipping\Domain\ValueObjects\CreateShipmentData;
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

    public function reconciliation(ShippingSettlementRequest $request): JsonResponse
    {
        $data = $request->validated();
        $from = $data['from'].' 00:00:00';
        $to = $data['to'].' 23:59:59';
        $shipments = Shipment::query()->with(['method', 'order.payments', 'order.returns' => fn ($query) => $query->whereBetween('created_at', [$from, $to])])
            ->whereBetween('created_at', [$from, $to])
            ->when($data['carrier'] ?? null, fn ($query, $carrier) => $query->whereHas('method', fn ($method) => $method->where('carrier', $carrier)))
            ->get();
        $rows = [];
        foreach ($shipments as $shipment) {
            $carrier = (string) ($shipment->method?->carrier ?: $shipment->method_code);
            $row = $rows[$carrier] ?? ['carrier' => $carrier, 'currency' => $shipment->currency, 'shipments' => 0, 'delivered' => 0, 'returned' => 0, 'cancelled' => 0, 'gross_cod_amount' => 0, 'collected_cod_amount' => 0, 'pending_cod_amount' => 0, 'refund_amount' => 0, 'shipping_fees' => 0, 'return_fees' => 0, 'expected_amount' => 0, 'shipment_ids' => []];
            $order = $shipment->order;
            $isCod = $order?->payments?->contains(fn ($payment) => $payment->method === 'cash_on_delivery');
            $collected = $isCod ? (int) $order->payments->where('method', 'cash_on_delivery')->whereIn('status', ['succeeded', 'paid', 'confirmed'])->sum('amount') : 0;
            $gross = $isCod && in_array($shipment->status, ['delivered', 'returned'], true) ? (int) ($order?->total_amount ?? 0) : 0;
            $returns = ($order?->returns ?? collect())->whereNotIn('status', ['rejected', 'cancelled']);
            $refund = $returns->whereIn('status', ['approved', 'received', 'completed', 'refunded'])->sum('refund_amount');
            $returned = $shipment->status === 'returned' || $returns->isNotEmpty();
            $row['shipments']++;
            $row[$shipment->status] = ($row[$shipment->status] ?? 0) + 1;
            if ($shipment->status === 'delivered') {
                $row['delivered']++;
            }
            if ($returned) {
                $row['returned']++;
            }
            if ($shipment->status === 'cancelled') {
                $row['cancelled']++;
            }
            $row['gross_cod_amount'] += $gross;
            $row['collected_cod_amount'] += $collected;
            $row['pending_cod_amount'] += max($gross - $collected, 0);
            $row['refund_amount'] += (int) $refund;
            $row['shipping_fees'] += (int) $shipment->fee;
            if ($returned) {
                $row['return_fees'] += (int) $shipment->fee;
            }
            $row['shipment_ids'][] = $shipment->id;
            $row['expected_amount'] = $row['collected_cod_amount'] - $row['shipping_fees'] - $row['return_fees'] - $row['refund_amount'];
            $rows[$carrier] = $row;
        }
        $settlements = CarrierSettlement::query()->whereDate('period_start', '<=', $data['to'])->whereDate('period_end', '>=', $data['from'])
            ->when($data['carrier'] ?? null, fn ($query, $carrier) => $query->where('carrier', $carrier))->get();
        $settled = $settlements->groupBy('carrier')->map(fn ($items) => ['settlements' => $items->count(), 'expected_amount' => $items->sum('expected_amount'), 'paid_amount' => $items->sum('paid_amount'), 'difference' => $items->sum('difference'), 'statuses' => $items->groupBy('status')->map->count()]);

        return response()->json(['data' => ['from' => $data['from'], 'to' => $data['to'], 'carriers' => collect($rows)->map(function (array $row) use ($settled) {
            $row['recorded_settlements'] = $settled->get($row['carrier'], ['settlements' => 0, 'expected_amount' => 0, 'paid_amount' => 0, 'difference' => 0, 'statuses' => []]);

            return $row;
        })->values(), 'settlements_without_shipments' => $settlements->pluck('carrier')->diff(array_keys($rows))->unique()->values()]]);
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

    public function statement(ShippingSettlementRequest $request, int $settlement): JsonResponse
    {
        $result = DB::transaction(function () use ($request, $settlement): array {
            $record = CarrierSettlement::query()->lockForUpdate()->findOrFail($settlement);
            abort_if($record->locked_at !== null || $record->approved_at !== null, 409, 'Approved settlements cannot be changed.');
            $handle = fopen($request->file('statement')->getRealPath(), 'rb');
            $headers = array_map(fn ($value) => strtolower(trim((string) $value)), fgetcsv($handle) ?: []);
            $trackingIndex = array_search('tracking_number', $headers, true);
            $paidIndex = array_search('paid_amount', $headers, true);
            abort_unless($trackingIndex !== false && $paidIndex !== false, 422, 'CSV must contain tracking_number and paid_amount columns.');
            $record->lines()->delete();
            $seen = [];
            $matched = 0;
            $missing = 0;
            $different = 0;
            while (($row = fgetcsv($handle)) !== false) {
                $tracking = trim((string) ($row[$trackingIndex] ?? ''));
                if ($tracking === '') {
                    continue;
                }
                $paid = (int) str_replace([',', ' '], '', (string) ($row[$paidIndex] ?? '0'));
                $shipment = Shipment::query()->with(['order.payments', 'method'])->where('tracking_number', $tracking)->first();
                $expected = $shipment ? $this->shipmentExpectedAmount($shipment) : 0;
                $status = $shipment ? ($paid === $expected ? 'matched' : 'amount_difference') : 'missing_shipment';
                $difference = $paid - $expected;
                if ($status === 'matched') {
                    $matched++;
                } else {
                    $status === 'missing_shipment' ? $missing++ : $different++;
                }
                CarrierSettlementLine::query()->create(['carrier_settlement_id' => $record->id, 'shipment_id' => $shipment?->id, 'tracking_number' => $tracking, 'carrier_status' => $row[array_search('status', $headers, true)] ?? null, 'expected_amount' => $expected, 'paid_amount' => $paid, 'difference' => $difference, 'match_status' => $status, 'difference_reason' => $status === 'amount_difference' ? 'Carrier amount differs from expected amount.' : ($status === 'missing_shipment' ? 'Tracking number not found.' : null), 'raw_data' => array_combine($headers, array_pad($row, count($headers), null))]);
                $seen[] = $tracking;
            }
            fclose($handle);
            $unreported = Shipment::query()->with(['order.payments', 'method'])->whereBetween('created_at', [$record->period_start.' 00:00:00', $record->period_end.' 23:59:59'])->whereHas('method', fn ($query) => $query->where('carrier', $record->carrier))->whereNotNull('tracking_number')->whereNotIn('tracking_number', $seen)->get();
            foreach ($unreported as $shipment) {
                CarrierSettlementLine::query()->create(['carrier_settlement_id' => $record->id, 'shipment_id' => $shipment->id, 'tracking_number' => $shipment->tracking_number, 'expected_amount' => $this->shipmentExpectedAmount($shipment), 'match_status' => 'missing_in_statement', 'difference_reason' => 'Shipment was not included in carrier statement.']);
                $missing++;
            }
            $record->update(['statement_file_name' => $request->file('statement')->getClientOriginalName(), 'expected_amount' => $record->lines()->sum('expected_amount'), 'paid_amount' => $record->lines()->sum('paid_amount'), 'difference' => $record->lines()->sum('difference'), 'status' => 'disputed']);
            AuditLog::query()->create(['actor_id' => $request->user()->id, 'action' => 'shipping.settlement.statement_imported', 'target_type' => CarrierSettlement::class, 'target_id' => $record->id, 'metadata' => ['matched' => $matched, 'missing' => $missing, 'different' => $different]]);

            return ['settlement' => $record->fresh('lines'), 'summary' => compact('matched', 'missing', 'different')];
        });

        return response()->json(['data' => $result]);
    }

    public function approve(ShippingSettlementRequest $request, int $settlement): JsonResponse
    {
        $record = DB::transaction(function () use ($request, $settlement): CarrierSettlement {
            $record = CarrierSettlement::query()->lockForUpdate()->findOrFail($settlement);
            abort_if($record->locked_at !== null || $record->approved_at !== null, 409, 'Settlement is already approved and locked.');
            abort_if($record->lines()->whereIn('match_status', ['missing_shipment', 'missing_in_statement', 'amount_difference'])->exists(), 422, 'Settlement has unresolved differences.');
            $record->update(['status' => 'approved', 'approved_at' => now(), 'approved_by' => $request->user()->id, 'locked_at' => now(), 'locked_by' => $request->user()->id]);
            AuditLog::query()->create(['actor_id' => $request->user()->id, 'action' => 'shipping.settlement.approved', 'target_type' => CarrierSettlement::class, 'target_id' => $record->id]);

            return $record->fresh(['lines', 'approver', 'locker']);
        });

        return response()->json(['data' => $record]);
    }

    private function shipmentExpectedAmount(Shipment $shipment): int
    {
        $isCod = $shipment->order?->payments?->contains(fn ($payment) => $payment->method === 'cash_on_delivery' && in_array($payment->status, ['succeeded', 'paid', 'confirmed'], true));
        if (! $isCod || ! in_array($shipment->status, ['delivered', 'returned'], true)) {
            return 0;
        }

        return max((int) $shipment->order->total_amount - (int) $shipment->fee, 0);
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
            if ($shipment->status === 'cancelled') {
                $row['return_fees'] += (int) $shipment->fee;
            }
            $isCod = $shipment->order?->payments?->contains(fn ($payment) => $payment->method === 'cash_on_delivery');
            if ($shipment->status === 'delivered' && $isCod) {
                $row['gross_cod_amount'] += (int) $shipment->order->total_amount;
            }
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

    public function customerMethods(ShippingRequest $request, ListShippingMethods $methods): JsonResponse
    {
        return response()->json(['data' => $methods->execute()]);
    }

    public function customerShipments(ShippingRequest $request, int $orderId, ListOrderShipments $shipments): JsonResponse
    {
        return response()->json(['data' => $shipments->execute($orderId)]);
    }

    public function createShipment(ShippingRequest $request, int $orderId, CreateShipment $create): JsonResponse
    {
        $data = $request->validated();

        return response()->json(['data' => $create->execute($orderId, new CreateShipmentData((int) $data['shipping_method_id'], $data['idempotency_key']))], 201);
    }

    public function index(ShippingRequest $request, ListAllShippingMethods $methods): JsonResponse
    {
        return response()->json(['data' => $methods->execute()]);
    }

    public function show(ShippingRequest $request, int $id, GetShippingMethod $method): JsonResponse
    {
        return response()->json(['data' => $method->execute($id)]);
    }

    public function store(ShippingRequest $request, CreateShippingMethod $create): JsonResponse
    {
        return response()->json(['data' => $create->execute($request->validated())], 201);
    }

    public function update(ShippingRequest $request, int $id, UpdateShippingMethod $update): JsonResponse
    {
        return response()->json(['data' => $update->execute($id, $request->validated())]);
    }

    public function destroy(ShippingRequest $request, int $id, DeleteShippingMethod $delete): JsonResponse
    {
        $delete->execute($id);

        return response()->json(null, 204);
    }

    public function status(ShippingRequest $request, int $id, UpdateShipmentStatus $update): JsonResponse
    {
        $data = $request->validated();

        return response()->json(['data' => $update->execute($id, $data['status'], $data['note'] ?? null)]);
    }
}
