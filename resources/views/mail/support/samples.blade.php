<x-mail::message>
@if ($forCustomer)
# Your samples are on their way, {{ $request->shipping['name'] }}.

We are cutting swatches of the cloths below and will post them to you. Hold them up to the light, feel the weight, and come back to the designer when you have found the one.
@else
# New sample request

**From:** {{ $request->shipping['name'] }} ({{ $request->email }})
@endif

<x-mail::table>
| Fabric |
|:-------|
@foreach ($request->fabrics as $fabric)
| {{ $fabric['name'] }} |
@endforeach
</x-mail::table>

**Delivery**
{{ $request->shipping['name'] }}
{{ collect([$request->shipping['address'] ?? null, $request->shipping['address2'] ?? null])->filter()->implode(', ') }}
{{ $request->shipping['city'] }} {{ $request->shipping['postcode'] }}, {{ $request->shipping['country'] }}

@if ($request->notes)
**Notes:** {{ $request->notes }}
@endif

@if ($forCustomer)
<x-mail::button :url="url('/design')">
Design your suit
</x-mail::button>
@else
<x-mail::button :url="url('/admin/sample-requests')">
Open in the admin
</x-mail::button>
@endif

Custom Tailor
</x-mail::message>
