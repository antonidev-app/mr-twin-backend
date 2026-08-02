<?php

namespace App\Mail;

use App\Models\LocalOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrderStatusUpdatedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public LocalOrder $order) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Pesanan {$this->order->order_number} — {$this->statusLabel()}",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.orders.status-updated',
            with: [
                'statusLabel' => $this->statusLabel(),
                'orderUrl' => rtrim(config('services.frontend_url'), '/')."/orders/{$this->order->id}",
            ],
        );
    }

    protected function statusLabel(): string
    {
        return match ($this->order->status) {
            'pending' => 'Menunggu diproses',
            'processing' => 'Sedang diproses',
            'shipped' => 'Telah dikirim',
            'completed' => 'Selesai',
            'cancelled' => 'Dibatalkan',
            default => $this->order->status,
        };
    }
}
