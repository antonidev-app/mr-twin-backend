<?php

namespace App\Http\Controllers\Customer;

use App\Exceptions\Accurate\AccurateException;
use App\Exceptions\Payment\MidtransException;
use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\LocalOrder;
use App\Models\ProductDisplay;
use App\Services\Accurate\AccurateClient;
use App\Services\Payment\MidtransClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class OrderController extends Controller
{
    public function __construct(
        protected AccurateClient $accurateClient,
        protected MidtransClient $midtransClient,
    ) {}

    public function index(Request $request)
    {
        return OrderResource::collection(
            $request->user()->orders()->latest()->paginate(15)
        );
    }

    public function show(Request $request, LocalOrder $order)
    {
        // Deliberately 404, not 403 — don't leak an order's existence to a
        // non-owner via a different status code.
        abort_unless($order->customer_id === $request->user()->id, 404);

        return new OrderResource($order->load('items'));
    }

    public function store(Request $request)
    {
        $customer = $request->user();

        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:product_display,id'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'shipping_name' => ['required', 'string', 'max:255'],
            'shipping_phone' => ['required', 'string', 'max:50'],
            'shipping_address' => ['required', 'string'],
        ]);

        // Gabung quantity per product_id kalau client kirim produk yang sama 2x.
        $requested = collect($validated['items'])
            ->groupBy('product_id')
            ->map(fn ($rows) => $rows->sum('quantity'));

        $products = ProductDisplay::with('item')
            ->whereIn('id', $requested->keys())
            ->where('is_published', true)
            ->get()
            ->keyBy('id');

        if ($products->count() !== $requested->count()) {
            return response()->json(['message' => 'One or more products are unavailable.'], 422);
        }

        // Cek stok live SEBELUM transaksi DB — panggilan HTTP eksternal lambat,
        // jangan menahan koneksi/transaksi DB sambil menunggunya.
        $lineItems = [];
        foreach ($requested as $productId => $quantity) {
            $item = $products[$productId]->item;

            try {
                $detail = $this->accurateClient->request('GET', 'item/detail.do', ['id' => $item->accurate_id]);
            } catch (AccurateException $e) {
                Log::error('checkout.stock_check_failed', ['item_id' => $item->id, 'error' => $e->getMessage()]);

                return response()->json([
                    'message' => "Unable to verify live stock for {$item->name}. Please try again shortly.",
                ], 503);
            }

            $availableToSell = $detail['d']['availableToSell'] ?? 0;

            if ($quantity > $availableToSell) {
                return response()->json([
                    'message' => "Insufficient stock for {$item->name}.",
                    'sku' => $item->sku,
                    'available' => $availableToSell,
                ], 422);
            }

            // Harga SELALU dari synced_items — tidak pernah percaya harga dari client.
            $lineItems[] = ['item' => $item, 'quantity' => $quantity, 'unit_price' => $item->unit_price];
        }

        $order = DB::transaction(function () use ($customer, $lineItems, $validated) {
            $order = LocalOrder::create([
                'customer_id' => $customer->id,
                'order_number' => 'ORD-'.strtoupper(Str::ulid()),
                'status' => 'pending',
                'payment_status' => 'unpaid',
                'expires_at' => now()->addMinutes(config('services.orders.payment_expiry_minutes')),
                'total_amount' => collect($lineItems)->sum(fn ($li) => $li['unit_price'] * $li['quantity']),
                'shipping_name' => $validated['shipping_name'],
                'shipping_phone' => $validated['shipping_phone'],
                'shipping_address' => $validated['shipping_address'],
            ]);

            foreach ($lineItems as $li) {
                $order->items()->create([
                    'item_id' => $li['item']->id,
                    'item_name' => $li['item']->name,
                    'sku' => $li['item']->sku,
                    'unit_price' => $li['unit_price'],
                    'quantity' => $li['quantity'],
                    'subtotal' => $li['unit_price'] * $li['quantity'],
                ]);
            }

            return $order;
        });

        // Midtrans is a slow external call — deliberately outside the DB
        // transaction (same rationale as the stock check above). The order
        // already exists either way; a failure here just means the customer
        // retries payment via POST /orders/{order}/pay instead of paying
        // immediately.
        $this->issuePaymentToken($order);

        return new OrderResource($order->load('items'));
    }

    public function pay(Request $request, LocalOrder $order)
    {
        abort_unless($order->customer_id === $request->user()->id, 404);

        if ($order->payment_status === 'paid') {
            return response()->json(['message' => 'Order sudah dibayar.'], 422);
        }

        // App-level deadline, checked independently of the Midtrans webhook
        // (which only flips payment_status to 'expired' once ITS OWN
        // transaction expiry fires — see custom `expiry` set below). This
        // guard closes the gap where the webhook hasn't landed yet but the
        // order's own deadline has already passed.
        if ($order->isExpired()) {
            if ($order->payment_status !== 'expired') {
                $order->update(['payment_status' => 'expired']);
            }

            return response()->json(['message' => 'Batas waktu pembayaran pesanan ini sudah lewat.'], 422);
        }

        $this->issuePaymentToken($order);

        return new OrderResource($order->load('items'));
    }

    protected function issuePaymentToken(LocalOrder $order): void
    {
        try {
            $payment = $this->midtransClient->createSnapTransaction($order->load('customer'));
            $order->update([
                'snap_token' => $payment['token'],
                'midtrans_order_id' => $payment['midtrans_order_id'],
            ]);
        } catch (MidtransException $e) {
            Log::error('checkout.payment_token_failed', ['order_id' => $order->id, 'error' => $e->getMessage()]);
        }
    }
}
