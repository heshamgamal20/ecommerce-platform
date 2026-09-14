<?php
namespace App\Modules\Order\Presentation\Http\Controllers;
use App\Http\Controllers\Controller;
use App\Modules\Order\Application\UseCases\ManageInvoices;
use App\Modules\Order\Presentation\Http\Requests\InvoiceRequest;
use Illuminate\Http\JsonResponse;
final class InvoiceController extends Controller
{
    public function show(InvoiceRequest $request, int $orderId, ManageInvoices $invoices): JsonResponse { return response()->json(['data'=>$invoices->show($orderId)]); }
    public function issue(InvoiceRequest $request, int $orderId, ManageInvoices $invoices): JsonResponse { return response()->json(['data'=>$invoices->issue($orderId)], 201); }
    public function cancel(InvoiceRequest $request, int $invoiceId, ManageInvoices $invoices): JsonResponse { return response()->json(['data'=>$invoices->cancel($invoiceId)]); }
    public function creditNote(InvoiceRequest $request, int $invoiceId, ManageInvoices $invoices): JsonResponse { $data=$request->validated(); return response()->json(['data'=>$invoices->credit($invoiceId, $data['return_id'] ?? null, (int)$data['amount'], $data['reason'] ?? null)], 201); }
}
