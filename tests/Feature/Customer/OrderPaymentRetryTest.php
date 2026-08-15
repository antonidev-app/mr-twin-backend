<?php

namespace Tests\Feature\Customer;

use App\Models\Customer;
use App\Models\LocalOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OrderPaymentRetryTest extends TestCase
{
    use RefreshDatabase;

    protected function makeOrder(Customer $customer, string $paymentStatus = 'unpaid'): LocalOrder
    {
        return LocalOrder::create([
            'customer_id' => $customer->id,
            'order_number' => 'ORD-RETRY-1',
            'status' => 'pending',
            'payment_status' => $paymentStatus,
            'total_amount' => 100000,
            'shipping_name' => $customer->name,
            'shipping_phone' => '081234567890',
            'shipping_address' => 'Jl. Contoh No. 1',
        ]);
    }

    public function test_it_issues_a_fresh_token_when_unpaid(): void
    {
        Http::fake(['*/snap/v1/transactions' => Http::response(['token' => 'fresh-token'])]);

        $customer = Customer::factory()->create();
        $order = $this->makeOrder($customer);

        $response = $this->actingAs($customer, 'sanctum')->postJson("/api/orders/{$order->id}/pay");

        $response->assertOk();
        $response->assertJsonPath('data.snap_token', 'fresh-token');
        $this->assertDatabaseHas('local_orders', ['id' => $order->id, 'snap_token' => 'fresh-token']);
    }

    public function test_it_rejects_retry_when_already_paid(): void
    {
        $customer = Customer::factory()->create();
        $order = $this->makeOrder($customer, 'paid');

        $response = $this->actingAs($customer, 'sanctum')->postJson("/api/orders/{$order->id}/pay");

        $response->assertStatus(422);
    }

    public function test_it_404s_for_a_non_owned_order(): void
    {
        $owner = Customer::factory()->create();
        $order = $this->makeOrder($owner);

        $otherCustomer = Customer::factory()->create();

        $response = $this->actingAs($otherCustomer, 'sanctum')->postJson("/api/orders/{$order->id}/pay");

        $response->assertNotFound();
    }
}
