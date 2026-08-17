<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\LocalOrder;
use App\Models\WebhookEvent;
use App\Services\Payment\MidtransClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MidtransWebhookController extends Controller
{
    public function __invoke(Request $request, MidtransClient $client)
    {
        $payload = $request->validate([
            'order_id' => ['required', 'string'],
            'status_code' => ['required', 'string'],
            'gross_amount' => ['required', 'string'],
            'signature_key' => ['required', 'string'],
            'transaction_status' => ['required', 'string'],
            'fraud_status' => ['nullable', 'string'],
            'payment_type' => ['nullable', 'string'],
        ]);

        if (! $client->verifySignature($payload)) {
            Log::warning('midtrans.webhook.invalid_signature', ['order_id' => $payload['order_id']]);

            return response()->json(['message' => 'Invalid signature.'], 403);
        }

        $eventId = "{$payload['order_id']}-{$payload['transaction_status']}";

        if (WebhookEvent::where('event_id', $eventId)->exists()) {
            return response()->json(['message' => 'Already processed.']);
        }

        $order = LocalOrder::where('midtrans_order_id', $payload['order_id'])->first();

        if (! $order) {
            Log::error('midtrans.webhook.order_not_found', ['order_id' => $payload['order_id']]);

            return response()->json(['message' => 'Order not found.'], 404);
        }

        DB::transaction(function () use ($payload, $eventId, $order) {
            WebhookEvent::create([
                'provider' => 'midtrans',
                'event_id' => $eventId,
                'payload' => $payload,
            ]);

            $this->applyTransactionStatus($order, $payload);
        });

        return response()->json(['message' => 'OK']);
    }

    protected function applyTransactionStatus(LocalOrder $order, array $payload): void
    {
        $status = $payload['transaction_status'];

        $isPaid = $status === 'settlement'
            || ($status === 'capture' && ($payload['fraud_status'] ?? null) === 'accept');

        if ($isPaid) {
            $order->update([
                'payment_status' => 'paid',
                'payment_method' => $payload['payment_type'] ?? null,
                'paid_at' => now(),
                'status' => 'processing',
            ]);

            return;
        }

        if (in_array($status, ['deny', 'cancel'], true)) {
            $order->update(['payment_status' => 'failed']);

            return;
        }

        if ($status === 'expire') {
            $order->update(['payment_status' => 'expired']);
        }

        // pending / anything else: no order mutation, WebhookEvent row above
        // is already the audit trail.
    }
}
