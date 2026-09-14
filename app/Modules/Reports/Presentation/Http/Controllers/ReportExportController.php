<?php

namespace App\Modules\Reports\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Reports\Presentation\Http\Requests\ReportRequest;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

final class ReportExportController extends Controller
{
    public function sales(ReportRequest $request): Response
    {
        $from = $request->validated('from').' 00:00:00';
        $to = $request->validated('to').' 23:59:59';
        $orders = DB::table('customer_orders')->whereBetween('created_at', [$from, $to])->orderBy('id')->get(['id', 'user_id', 'status', 'currency', 'subtotal_amount', 'discount_amount', 'tax_amount', 'shipping_amount', 'total_amount', 'created_at']);
        return $this->csv('sales-'.$request->validated('from').'-'.$request->validated('to').'.csv', ['id', 'user_id', 'status', 'currency', 'subtotal_amount', 'discount_amount', 'tax_amount', 'shipping_amount', 'total_amount', 'created_at'], $orders);
    }

    public function payments(ReportRequest $request): Response
    {
        $from = $request->validated('from').' 00:00:00';
        $to = $request->validated('to').' 23:59:59';
        $payments = DB::table('payments')->whereBetween('created_at', [$from, $to])->orderBy('id')->get(['id', 'order_id', 'user_id', 'method', 'provider_reference', 'amount', 'currency', 'status', 'created_at']);
        return $this->csv('payments-'.$request->validated('from').'-'.$request->validated('to').'.csv', ['id', 'order_id', 'user_id', 'method', 'provider_reference', 'amount', 'currency', 'status', 'created_at'], $payments);
    }

    public function customers(ReportRequest $request): Response
    {
        return $this->exportRange($request, 'customers', 'users', ['id', 'name', 'email', 'phone', 'role', 'created_at']);
    }

    public function products(ReportRequest $request): Response
    {
        return $this->exportRange($request, 'products', 'products', ['id', 'name', 'sku', 'status', 'type', 'brand_id', 'category_id', 'created_at']);
    }

    public function inventory(ReportRequest $request): Response
    {
        return $this->exportRange($request, 'inventory', 'inventory_items', ['id', 'product_id', 'variant_id', 'on_hand', 'reserved', 'created_at', 'updated_at']);
    }

    public function returns(ReportRequest $request): Response
    {
        return $this->exportRange($request, 'returns', 'order_returns', ['id', 'order_id', 'user_id', 'status', 'reason', 'refund_amount', 'created_at', 'updated_at']);
    }

    public function shipments(ReportRequest $request): Response
    {
        return $this->exportRange($request, 'shipments', 'shipments', ['id', 'order_id', 'user_id', 'method_code', 'tracking_number', 'status', 'fee', 'currency', 'created_at', 'updated_at']);
    }

    public function settlements(ReportRequest $request): Response
    {
        return $this->exportRange($request, 'settlements', 'carrier_settlements', ['id', 'carrier', 'period_start', 'period_end', 'status', 'gross_cod_amount', 'shipping_fees', 'return_fees', 'other_adjustments', 'expected_amount', 'paid_amount', 'difference', 'created_at']);
    }

    public function audit(ReportRequest $request): Response
    {
        return $this->exportRange($request, 'audit-logs', 'audit_logs', ['id', 'actor_id', 'action', 'target_type', 'target_id', 'created_at']);
    }

    private function exportRange(ReportRequest $request, string $name, string $table, array $headers): Response
    {
        $from = $request->validated('from').' 00:00:00';
        $to = $request->validated('to').' 23:59:59';
        $rows = DB::table($table)->whereBetween('created_at', [$from, $to])->orderBy('id')->get($headers);
        return $this->csv($name.'-'.$request->validated('from').'-'.$request->validated('to').'.csv', $headers, $rows);
    }

    private function csv(string $filename, array $headers, iterable $rows): Response
    {
        $output = fopen('php://temp', 'r+');
        fputcsv($output, $headers);
        foreach ($rows as $row) {
            fputcsv($output, array_map(fn ($header) => data_get($row, $header), $headers));
        }
        rewind($output);
        $content = stream_get_contents($output);
        fclose($output);
        return response($content, 200, ['Content-Type' => 'text/csv; charset=UTF-8', 'Content-Disposition' => 'attachment; filename="'.$filename.'"']);
    }
}
