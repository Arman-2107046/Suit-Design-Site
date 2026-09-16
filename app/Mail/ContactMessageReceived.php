<?php

namespace App\Mail;

use App\Models\ContactMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ContactMessageReceived extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public ContactMessage $message) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'New message: ' . ($this->message->subject ?: 'Contact form'),
            replyTo: [$this->message->email],
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.support.contact', with: ['message' => $this->message]);
    }
}
