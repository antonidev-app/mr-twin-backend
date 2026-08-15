<?php

namespace App\Services\Payment;

use App\Exceptions\Payment\MidtransException;
use App\Models\LocalOrder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MidtransClient
{
    public function __construct(
        protected string $serverKey,
        protected string $clientKey,
        protected bool $isProduction,
    ) {}

    public function createSnapTransaction(LocalOrder $order): string
    {
        $grossAmount = (int) $order->total_amount;

        $response = Http::withBasicAuth($this->serverKey, '')
            ->withHeaders(['Accept' => 'application/json'])
            ->post("{$this->snapBaseUrl()}/snap/v1/transactions", [
                'transaction_details' => [
                    'order_id' => $order->order_number,
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

        return $json['token'];
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
