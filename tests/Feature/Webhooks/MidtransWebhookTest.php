<?php

namespace Tests\Feature\Webhooks;

use App\Mail\OrderStatusUpdatedMail;
use App\Models\Customer;
use App\Models\LocalOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class MidtransWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.midtrans.server_key' => 'test-server-key']);
    }

    protected function makeOrder(): LocalOrder
    {
        $customer = Customer::factory()->create();

        return LocalOrder::create([
            'customer_id' => $customer->id,
            'order_number' => 'ORD-WEBHOOK-1',
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'total_amount' => 150000,
            'shipping_name' => $customer->name,
            'shipping_phone' => '081234567890',
            'shipping_address' => 'Jl. Contoh No. 1',
        ]);
    }

    protected function signedPayload(LocalOrder $order, string $transactionStatus, ?string $fraudStatus = null): array
    {
        $payload = [
            'order_id' => $order->order_number,
            'status_code' => '200',
            'gross_amount' => (string) $order->total_amount,
            'transaction_status' => $transactionStatus,
            'fraud_status' => $fraudStatus,
            'payment_type' => 'credit_card',
        ];

        $payload['signature_key'] = hash(
            'sha512',
            $payload['order_id'].$payload['status_code'].$payload['gross_amount'].'test-server-key'
        );

        return $payload;
    }

    public function test_settlement_marks_order_paid_and_queues_status_email(): void
    {
        Mail::fake();
        $order = $this->makeOrder();

        $response = $this->postJson('/api/webhooks/midtrans', $this->signedPayload($order, 'settlement'));

        $response->assertOk();
        $this->assertDatabaseHas('local_orders', [
            'id' => $order->id,
            'payment_status' => 'paid',
            'status' => 'processing',
        ]);
        Mail::assertQueued(OrderStatusUpdatedMail::class, fn ($mail) => $mail->hasTo($order->customer->email));
    }

    public function test_invalid_signature_is_rejected(): void
    {
        $order = $this->makeOrder();
        $payload = $this->signedPayload($order, 'settlement');
        $payload['signature_key'] = 'tampered';

        $response = $this->postJson('/api/webhooks/midtrans', $payload);

        $response->assertStatus(403);
        $this->assertDatabaseHas('local_orders', ['id' => $order->id, 'payment_status' => 'unpaid']);
    }

    public function test_duplicate_events_are_processed_only_once(): void
    {
        Mail::fake();
        $order = $this->makeOrder();
        $payload = $this->signedPayload($order, 'settlement');

        $this->postJson('/api/webhooks/midtrans', $payload)->assertOk();
        $this->postJson('/api/webhooks/midtrans', $payload)->assertOk();

        $this->assertDatabaseCount('webhook_events', 1);
        Mail::assertQueuedCount(1);
    }

    public function test_unknown_order_returns_404(): void
    {
        $payload = [
            'order_id' => 'ORD-DOES-NOT-EXIST',
            'status_code' => '200',
            'gross_amount' => '150000.00',
            'transaction_status' => 'settlement',
        ];
        $payload['signature_key'] = hash(
            'sha512',
            $payload['order_id'].$payload['status_code'].$payload['gross_amount'].'test-server-key'
        );

        $response = $this->postJson('/api/webhooks/midtrans', $payload);

        $response->assertNotFound();
    }
}
