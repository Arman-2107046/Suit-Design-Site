<x-mail::message>
# Thank you, {{ $order->shipping['name'] }}.

Your order **{{ $order->number }}** is in our hands. We will review your measurements, reserve your cloth, and let you know as your suit moves through the workshop.

<x-mail::table>
| Suit | Cloth | Price |
|:-----|:------|------:|
@foreach ($order->items as $item)
| Tailored suit{{ $item->quantity > 1 ? " × {$item->quantity}" : '' }} | {{ $item->fabric_name }} | ${{ number_format($item->price * $item->quantity, 0) }} |
@endforeach
| | **Total** (free shipping) | **${{ number_format($order->total, 0) }}** |
</x-mail::table>

**Delivery**
{{ $order->shipping['name'] }}
{{ collect([$order->shipping['address'] ?? null, $order->shipping['address2'] ?? null])->filter()->implode(', ') }}
{{ $order->shipping['city'] }} {{ $order->shipping['postcode'] }}, {{ $order->shipping['country'] }}

**Body profile** — {{ $order->body_profile['name'] }}: {{ $order->body_profile['height_cm'] }} cm · {{ $order->body_profile['weight_kg'] }} kg · {{ $order->body_profile['age'] }} years

**Payment** — {{ str($order->payment_method)->replace('_', ' ')->ucfirst() }}
@if ($order->payment_method === 'bank_transfer')
Production begins once your transfer clears. We will send bank details in a separate email.
@else
Nothing to pay now. You pay when your suit arrives.
@endif

<x-mail::button :url="$url">
View your order
</x-mail::button>

Made to measure,<br>
Custom Tailor
</x-mail::message>
