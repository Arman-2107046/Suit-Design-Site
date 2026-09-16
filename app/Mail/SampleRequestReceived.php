<?php

namespace App\Mail;

use App\Models\SampleRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SampleRequestReceived extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public SampleRequest $request, public bool $forCustomer = false) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->forCustomer ? 'Your fabric samples are on the way — Custom Tailor' : 'New sample request');
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.support.samples', with: ['request' => $this->request, 'forCustomer' => $this->forCustomer]);
    }
}
