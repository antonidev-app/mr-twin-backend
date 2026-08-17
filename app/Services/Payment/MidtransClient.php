<?php

namespace App\Services\Payment;

use App\Exceptions\Payment\MidtransException;
use App\Models\LocalOrder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class MidtransClient
{
    public function __construct(
        protected string $serverKey,
        protected string $clientKey,
        protected bool $isProduction,
    ) {}

    /**
     * Midtrans permanently ties an `order_id` to one transaction lifecycle —
     * it rejects re-creating a transaction for an order_id that's already
     * been used, even once that prior attempt has expired. So every attempt
     * (checkout, and every retry via /pay) gets its own unique Midtrans
     * order_id, distinct from our stable `order_number`; the caller is
     * responsible for persisting the returned `midtrans_order_id` so the
     * webhook can look the order back up by it.
     *
     * @return array{token: string, midtrans_order_id: string}
     */
    public function createSnapTransaction(LocalOrder $order): array
    {
        $grossAmount = (int) $order->total_amount;
        $midtransOrderId = "{$order->order_number}-".Str::lower(Str::random(8));

        $response = Http::withBasicAuth($this->serverKey, '')
            ->withHeaders(['Accept' => 'application/json'])
            ->post("{$this->snapBaseUrl()}/snap/v1/transactions", [
                'transaction_details' => [
                    'order_id' => $midtransOrderId,
                    'gross_amount' => $grossAmount,
                ],
                'customer_details' => [
                    'first_name' => $order->customer->name,
                    'email' => $order->customer->email,
                    'phone' => $order->shipping_phone,
                ],
                'item_details' => [[
                    'id' => $order->order_number,
                    'price' => $grossAmount,
                    'quantity' => 1,
                    'name' => "Pesanan {$order->order_number}",
                ]],
                'callbacks' => [
                    'finish' => rtrim(config('services.frontend_url'), '/')."/orders/{$order->id}",
                ],
            ]);

        $json = $response->json() ?? [];

        if (! $response->successful() || ! isset($json['token'])) {
            Log::error('midtrans.snap.create_failed', [
                'order_id' => $order->id,
                'status' => $response->status(),
                'body' => $json,
            ]);
            throw new MidtransException($json['error_messages'][0] ?? 'Midtrans request failed');
        }

        return ['token' => $json['token'], 'midtrans_order_id' => $midtransOrderId];
    }

    public function verifySignature(array $payload): bool
    {
        $expected = hash(
            'sha512',
            ($payload['order_id'] ?? '').($payload['status_code'] ?? '').($payload['gross_amount'] ?? '').$this->serverKey
        );

        return hash_equals($expected, $payload['signature_key'] ?? '');
    }

    protected function snapBaseUrl(): string
    {
        return $this->isProduction
            ? 'https://app.midtrans.com'
            : 'https://app.sandbox.midtrans.com';
    }
}
