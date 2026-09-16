<x-filament-widgets::widget>
    <div style="border-radius: 1rem; background: #141414; color: #fff; padding: 1.75rem 2rem; display: flex; flex-wrap: wrap; gap: 1.5rem; align-items: flex-end; justify-content: space-between;">
        <div>
            <p style="font-size: 11px; font-weight: 600; letter-spacing: 0.24em; text-transform: uppercase; color: rgba(255,255,255,0.5);">{{ $date }}</p>
            <p style="margin-top: 0.5rem; font-size: 1.9rem; font-weight: 300; letter-spacing: -0.02em; line-height: 1.1;">{{ $greeting }}, {{ $name }}.</p>
            <p style="margin-top: 0.75rem; font-size: 0.9rem; color: rgba(255,255,255,0.7);">
                @if ($todo->isEmpty())
                    Nothing is waiting on you. {{ $inProduction }} suit{{ $inProduction === 1 ? ' is' : 's are' }} in the workshop.
                @else
                    Waiting on you:
                    @foreach ($todo as [$count, $label, $url])
                        <a href="{{ $url }}" style="color: #fff; text-decoration: underline; text-underline-offset: 4px;">{{ $count }} {{ $label }}</a>{{ ! $loop->last ? ' · ' : '' }}
                    @endforeach
                @endif
            </p>
        </div>
        <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
            <a href="{{ url('/') }}" target="_blank" style="display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.65rem 1.1rem; border-radius: 9999px; border: 1px solid rgba(255,255,255,0.3); font-size: 0.85rem; font-weight: 500; color: #fff;">View storefront</a>
            <a href="{{ url('/design') }}" target="_blank" style="display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.65rem 1.1rem; border-radius: 9999px; background: #fff; font-size: 0.85rem; font-weight: 500; color: #111;">Open the designer</a>
        </div>
    </div>
</x-filament-widgets::widget>
