<?php

namespace App\Observers;

use App\Mail\OrderStatusUpdatedMail;
use App\Models\LocalOrder;
use Illuminate\Support\Facades\Mail;

class LocalOrderObserver
{
    public function updated(LocalOrder $order): void
    {
        if ($order->wasChanged('status')) {
            $order->loadMissing(['items', 'customer']);
            Mail::to($order->customer->email)->queue(new OrderStatusUpdatedMail($order));
        }
    }
}
