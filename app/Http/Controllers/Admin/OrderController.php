<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\AdminOrderResource;
use App\Mail\OrderStatusUpdatedMail;
use App\Models\LocalOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;

class OrderController extends Controller
{
    protected const STATUSES = ['pending', 'processing', 'shipped', 'completed', 'cancelled'];

    public function index(Request $request)
    {
        $request->validate([
            'status' => ['nullable', Rule::in(self::STATUSES)],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $orders = LocalOrder::query()
            ->with('customer')
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->latest()
            ->paginate($request->integer('per_page', 20));

        return AdminOrderResource::collection($orders);
    }

    public function show(LocalOrder $order)
    {
        return new AdminOrderResource($order->load(['items', 'customer']));
    }

    public function update(Request $request, LocalOrder $order)
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(self::STATUSES)],
        ]);

        $order->update($validated);
        $order->load(['items', 'customer']);

        if ($order->wasChanged('status')) {
            Mail::to($order->customer->email)->queue(new OrderStatusUpdatedMail($order));
        }

        return new AdminOrderResource($order);
    }
}
