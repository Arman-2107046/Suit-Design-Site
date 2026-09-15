<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrderPlaced extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Order $order) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Your order {$this->order->number} — Custom Tailor");
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.orders.placed', with: [
            'order' => $this->order->load('items'),
            'url' => route('orders.show', $this->order),
        ]);
    }
}
