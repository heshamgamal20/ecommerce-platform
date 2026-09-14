<?php
namespace App\Modules\Order\Application\UseCases;
use App\Modules\Order\Domain\Contracts\InvoiceRepositoryInterface;
final class ManageInvoices
{
    public function __construct(private readonly InvoiceRepositoryInterface $invoices) {}
    public function show(int $orderId): object { return $this->invoices->get($orderId); }
    public function issue(int $orderId): object { return $this->invoices->issue($orderId); }
    public function cancel(int $invoiceId): object { return $this->invoices->cancel($invoiceId); }
    public function credit(int $invoiceId, ?int $returnId, int $amount, ?string $reason): object { return $this->invoices->issueCreditNote($invoiceId, $returnId, $amount, $reason); }
}
