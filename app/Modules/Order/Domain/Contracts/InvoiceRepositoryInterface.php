<?php
namespace App\Modules\Order\Domain\Contracts;
interface InvoiceRepositoryInterface
{
    public function get(int $orderId): object;
    public function issue(int $orderId): object;
    public function cancel(int $invoiceId): object;
    public function issueCreditNote(int $invoiceId, ?int $returnId, int $amount, ?string $reason): object;
}
