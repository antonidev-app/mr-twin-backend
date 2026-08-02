<x-mail::message>
# Update Pesanan {{ $order->order_number }}

Halo {{ $order->customer->name }},

Status pesanan kamu sekarang: **{{ $statusLabel }}**.

<x-mail::table>
| Item | Qty | Subtotal |
| :--- | :-: | -------: |
@foreach ($order->items as $item)
| {{ $item->item_name }} | {{ (int) $item->quantity }} | Rp {{ number_format($item->subtotal, 0, ',', '.') }} |
@endforeach
</x-mail::table>

**Total: Rp {{ number_format($order->total_amount, 0, ',', '.') }}**

<x-mail::button :url="$orderUrl">
Lihat Detail Pesanan
</x-mail::button>

Terima kasih sudah belanja di {{ config('app.name') }}.
</x-mail::message>
