<?php

namespace Tests\Feature\Admin;

use App\Mail\OrderStatusUpdatedMail;
use App\Models\Customer;
use App\Models\LocalOrder;
use App\Models\LocalOrderItem;
use App\Models\SyncedItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class OrderStatusMailTest extends TestCase
{
    use RefreshDatabase;

    protected function makeOrder(): LocalOrder
    {
        $customer = Customer::factory()->create();

        $order = LocalOrder::create([
            'customer_id' => $customer->id,
            'order_number' => 'ORD-0001',
            'status' => 'pending',
            'total_amount' => 150000,
            'shipping_name' => $customer->name,
            'shipping_phone' => '081234567890',
            'shipping_address' => 'Jl. Contoh No. 1',
        ]);

        $item = SyncedItem::create(['accurate_id' => 1, 'sku' => 'SKU-1', 'name' => 'Mouse Wireless']);

        LocalOrderItem::create([
            'local_order_id' => $order->id,
            'item_id' => $item->id,
            'item_name' => 'Mouse Wireless',
            'sku' => 'SKU-1',
            'unit_price' => 150000,
            'quantity' => 1,
            'subtotal' => 150000,
        ]);

        return $order;
    }

    protected function admin(): User
    {
        return User::factory()->create();
    }

    public function test_it_queues_an_email_when_status_changes(): void
    {
        Mail::fake();
        $order = $this->makeOrder();

        $this->actingAs($this->admin(), 'sanctum')
            ->patchJson("/api/admin/orders/{$order->id}", ['status' => 'processing'])
            ->assertOk();

        Mail::assertQueued(
            OrderStatusUpdatedMail::class,
            fn ($mail) => $mail->hasTo($order->customer->email)
        );
    }

    public function test_it_does_not_queue_an_email_when_status_is_unchanged(): void
    {
        Mail::fake();
        $order = $this->makeOrder();

        $this->actingAs($this->admin(), 'sanctum')
            ->patchJson("/api/admin/orders/{$order->id}", ['status' => 'pending'])
            ->assertOk();

        Mail::assertNothingQueued();
    }

    public function test_the_mail_renders_order_details(): void
    {
        $order = $this->makeOrder();
        $order->update(['status' => 'shipped']);
        $order->load(['items', 'customer']);

        $mailable = new OrderStatusUpdatedMail($order);

        $mailable->assertSeeInHtml($order->order_number);
        $mailable->assertSeeInHtml('Telah dikirim');
        $mailable->assertSeeInHtml('Mouse Wireless');
        $mailable->assertSeeInHtml('150.000');
    }
}
