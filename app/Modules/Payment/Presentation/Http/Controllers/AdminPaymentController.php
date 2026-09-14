<?php

namespace App\Modules\Payment\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\PaymentWebhookEvent;
use App\Models\ProviderCircuitBreaker;
use App\Modules\Payment\Presentation\Http\Requests\AdminPaymentRequest;
use Illuminate\Http\JsonResponse;

final class AdminPaymentController extends Controller
{
    public function index(AdminPaymentRequest $request): JsonResponse
    {
        $filters = $request->validated();
        $payments = Payment::query()->with(['order:id,status,total_amount,currency', 'user:id,name,email,phone'])->withCount('operations')
            ->when($filters['q'] ?? null, fn ($query, $q) => $query->where(fn ($inner) => $inner->where('provider_reference', 'like', '%'.$q.'%')->orWhere('id', is_numeric($q) ? (int) $q : 0)->orWhereHas('order', fn ($order) => $order->where('id', is_numeric($q) ? (int) $q : 0))))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['method'] ?? null, fn ($query, $method) => $query->where('method', $method))
            ->when($filters['provider_reference'] ?? null, fn ($query, $reference) => $query->where('provider_reference', $reference))
            ->when($filters['from'] ?? null, fn ($query, $from) => $query->whereDate('created_at', '>=', $from))
            ->when($filters['to'] ?? null, fn ($query, $to) => $query->whereDate('created_at', '<=', $to))
            ->latest('id')->paginate(min(max((int) ($filters['per_page'] ?? 25), 1), 100));
        return response()->json(['data' => $payments]);
    }

    public function show(AdminPaymentRequest $request, int $payment): JsonResponse
    {
        $item = Payment::query()->with(['order:id,status,total_amount,currency', 'user:id,name,email,phone', 'operations' => fn ($query) => $query->select(['id', 'payment_id', 'operation', 'status', 'provider_reference', 'attempt_count', 'last_error', 'next_retry_at', 'created_at', 'updated_at'])])->findOrFail($payment);
        $webhooks = PaymentWebhookEvent::query()->where('payment_reference', $item->provider_reference)->latest('id')->get(['id', 'provider', 'event_id', 'event_type', 'status', 'payment_reference', 'processing_error', 'processed_at', 'created_at']);
        return response()->json(['data' => ['payment' => $item, 'webhooks' => $webhooks]]);
    }

    public function exceptions(AdminPaymentRequest $request): JsonResponse
    {
        $filters = $request->validated();
        $payments = Payment::query()->with(['order:id,status,total_amount,currency', 'operations' => fn ($query) => $query->whereIn('status', ['failed', 'dead_letter'])->select(['id', 'payment_id', 'operation', 'status', 'attempt_count', 'last_error', 'next_retry_at', 'created_at'])])->where(function ($query): void {
            $query->whereIn('status', ['failed', 'abandoned'])->orWhereHas('operations', fn ($operation) => $operation->whereIn('status', ['failed', 'dead_letter']));
        })->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))->when($filters['method'] ?? null, fn ($query, $method) => $query->where('method', $method))->when($filters['from'] ?? null, fn ($query, $from) => $query->whereDate('created_at', '>=', $from))->when($filters['to'] ?? null, fn ($query, $to) => $query->whereDate('created_at', '<=', $to))->latest('id')->paginate(min(max((int) ($filters['per_page'] ?? 50), 1), 100));
        return response()->json(['data' => $payments, 'circuits' => ProviderCircuitBreaker::query()->get(['provider', 'failure_count', 'opened_until', 'last_failure_at'])]);
    }
}
