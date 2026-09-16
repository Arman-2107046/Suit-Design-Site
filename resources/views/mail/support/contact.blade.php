<x-mail::message>
# {{ $message->subject ?: 'New message from the contact form' }}

**From:** {{ $message->name }} ({{ $message->email }})

{{ $message->message }}

<x-mail::button :url="url('/admin/contact-messages')">
Open in the admin
</x-mail::button>

Custom Tailor
</x-mail::message>
