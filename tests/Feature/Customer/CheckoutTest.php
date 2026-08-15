<?php

namespace Tests\Feature\Customer;

use App\Models\AccurateConnection;
use App\Models\Customer;
use App\Models\ProductDisplay;
use App\Models\SyncedItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CheckoutTest extends TestCase
{
    use RefreshDatabase;

    protected function seedAccurateConnection(): void
    {
        AccurateConnection::create([
            'id' => 1,
            'access_token' => 'test-token',
            'refresh_token' => 'test-refresh',
            'token_expires_at' => now()->addHour(),
            'id_db' => '1',
            'db_alias' => 'Test DB',
            'session_id' => 'test-session',
            'session_host' => 'https://sandbox-db.accurate.id',
            'session_created_at' => now(),
        ]);
    }

    protected function makePublishedProduct(int $accurateId, float $price): ProductDisplay
    {
        $item = SyncedItem::create([
            'accurate_id' => $accurateId,
            'sku' => "SKU-{$accurateId}",
            'name' => "Produk {$accurateId}",
            'unit_price' => $price,
        ]);

        return ProductDisplay::create(['item_id' => $item->id, 'is_published' => true]);
    }

    protected function checkoutPayload(Customer $customer, ProductDisplay $product, float $quantity = 1): array
    {
        return [
            'items' => [['product_id' => $product->id, 'quantity' => $quantity]],
            'shipping_name' => $customer->name,
            'shipping_phone' => '081234567890',
            'shipping_address' => 'Jl. Contoh No. 1',
        ];
    }

    public function test_checkout_creates_order_and_issues_a_snap_token(): void
    {
        $this->seedAccurateConnection();
        Http::fake([
            '*/accurate/api/item/detail.do*' => Http::response(['s' => true, 'd' => ['availableToSell' => 10]]),
            '*/snap/v1/transactions' => Http::response(['token' => 'snap-token-123']),
        ]);

        $customer = Customer::factory()->create();
        $product = $this->makePublishedProduct(1, 100000);

        $response = $this->actingAs($customer, 'sanctum')
            ->postJson('/api/orders', $this->checkoutPayload($customer, $product, 2));

        $response->assertCreated();
        $response->assertJsonPath('data.payment_status', 'unpaid');
        $response->assertJsonPath('data.snap_token', 'snap-token-123');
        $this->assertDatabaseHas('local_orders', [
            'payment_status' => 'unpaid',
            'snap_token' => 'snap-token-123',
        ]);
    }

    public function test_checkout_still_creates_the_order_when_midtrans_is_unreachable(): void
    {
        $this->seedAccurateConnection();
        Http::fake([
            '*/accurate/api/item/detail.do*' => Http::response(['s' => true, 'd' => ['availableToSell' => 10]]),
            '*/snap/v1/transactions' => Http::response(['error_messages' => ['boom']], 500),
        ]);

        $customer = Customer::factory()->create();
        $product = $this->makePublishedProduct(2, 50000);

        $response = $this->actingAs($customer, 'sanctum')
            ->postJson('/api/orders', $this->checkoutPayload($customer, $product));

        $response->assertCreated();
        $response->assertJsonPath('data.snap_token', null);
        $this->assertDatabaseHas('local_orders', ['payment_status' => 'unpaid']);
    }
}
