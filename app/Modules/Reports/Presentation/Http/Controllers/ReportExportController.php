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
