<?php

namespace Tests\Feature;

use App\Models\CreditNote;
use App\Models\CustomerOrder;
use App\Models\Invoice;
use App\Models\OrderReturn;
use App\Models\Payment;
use App\Models\User;
use App\Modules\Order\Domain\Contracts\OrderRepositoryInterface;
use App\Modules\Order\Domain\Contracts\TransactionManagerInterface;
use App\Modules\Payment\Application\UseCases\RefundPayment;
use App\Modules\Payment\Domain\Contracts\PaymentGatewayInterface;
use App\Modules\Payment\Domain\Contracts\PaymentOperationRepositoryInterface;
use App\Modules\Payment\Domain\Contracts\PaymentRepositoryInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

final class PartialRefundTest extends TestCase
{
    use RefreshDatabase;

    public function test_return_refund_sends_final_amount_and_keeps_payment_partially_refunded(): void
    {
        $user = User::factory()->create();
        $order = CustomerOrder::query()->create([
            'user_id' => $user->id,
            'status' => 'confirmed',
            'total_amount' => 1000,
            'currency' => 'EGP',
        ]);
        $payment = Payment::query()->create([
            'order_id' => $order->id,
            'user_id' => $user->id,
            'method' => 'cash_on_delivery',
            'amount' => 1000,
            'currency' => 'EGP',
            'status' => 'confirmed',
            'idempotency_key' => 'partial-refund-test',
        ]);
        $return = OrderReturn::query()->create([
            'order_id' => $order->id,
            'payment_id' => $payment->id,
            'user_id' => $user->id,
            'status' => 'approved',
            'reason' => 'Damaged item',
            'refund_amount' => 300,
            'received_at' => now(),
            'inspection_status' => 'passed',
            'final_refund_amount' => 300,
        ]);
        $invoice = Invoice::query()->create([
            'order_id' => $order->id,
            'number' => 'INV-PARTIAL-TEST',
            'status' => 'issued',
            'currency' => 'EGP',
            'total_amount' => 1000,
        ]);
        CreditNote::query()->create([
            'invoice_id' => $invoice->id,
            'return_id' => $return->id,
            'number' => 'CN-PARTIAL-TEST',
            'status' => 'issued',
            'currency' => 'EGP',
            'amount' => 300,
        ]);

        $payments = Mockery::mock(PaymentRepositoryInterface::class);
        $payments->shouldReceive('find')->with($payment->id)->andReturn($payment);
        $payments->shouldReceive('findForUpdate')->with($payment->id)->andReturn($payment);
        $payments->shouldReceive('updateStatus')->once()->with($payment, 'confirmed', Mockery::on(fn (array $attributes): bool => $attributes['refunded_amount'] === 300))->andReturnUsing(function ($model, $status, $attributes) {
            $model->fill($attributes);
            $model->status = $status;
            return $model;
        });

        $operations = Mockery::mock(PaymentOperationRepositoryInterface::class);
        $operations->shouldReceive('successfulResponse')->once()->andReturnNull();
        $operations->shouldReceive('start')->once();
        $operations->shouldReceive('acquireLease')->once()->andReturnTrue();
        $operations->shouldReceive('complete')->once();

        $gateway = Mockery::mock(PaymentGatewayInterface::class);
        $gateway->shouldReceive('refundPayment')->once()->with($payment, 300)->andReturn(['status' => 'refunded', 'metadata' => []]);

        $orders = Mockery::mock(OrderRepositoryInterface::class);
        $orders->shouldNotReceive('markRefunded');

        $transactions = Mockery::mock(TransactionManagerInterface::class);
        $transactions->shouldReceive('run')->once()->andReturnUsing(fn ($operation) => $operation());

        $result = (new RefundPayment($payments, $operations, $gateway, $orders, $transactions))->execute($payment->id);

        self::assertSame('confirmed', $result->status);
        self::assertSame(300, (int) $result->refunded_amount);
        $this->assertDatabaseHas('order_returns', ['id' => $return->id, 'status' => 'refunded']);
        $this->assertNotNull(OrderReturn::query()->findOrFail($return->id)->refunded_at);
    }
}
