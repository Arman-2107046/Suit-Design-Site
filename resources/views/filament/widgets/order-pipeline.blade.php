{{-- One root element: Livewire attaches to the first, so the styles live inside it --}}
<x-filament-widgets::widget>
    @include('filament.widgets.partials.dashboard-styles')

    <x-filament::section heading="Order pipeline" description="Every live order, by stage.">
        <x-slot name="afterHeader">
            <span class="dw-total dw-num">{{ number_format($active) }} live</span>
        </x-slot>

        <style>
            .op-row { display: grid; grid-template-columns: 10px minmax(0, 1fr) auto; align-items: center; gap: 4px 10px; padding: 9px 8px; margin: 0 -8px; border-radius: 10px; color: inherit; text-decoration: none; transition: background 0.15s ease; }
            .op-row:hover { background: #f8fafc; }
            .op-dot { width: 8px; height: 8px; border-radius: 50%; }
            .op-label { font-size: 13px; font-weight: 500; color: #1e293b; }
            .op-count { font-size: 14px; font-weight: 600; text-align: right; }
            .op-share { font-size: 11px; color: #94a3b8; margin-left: 4px; font-weight: 500; }
            .op-row .dw-track { grid-column: 2 / 4; }
        </style>

        <div class="dw">
            @foreach ($stages as $stage)
                <a href="{{ $stage['url'] }}" class="op-row" title="See {{ strtolower($stage['label']) }} orders">
                    <span class="op-dot" style="background: {{ $stage['tint'] }};"></span>
                    <span class="op-label">{{ $stage['label'] }}</span>
                    <span class="op-count dw-num">{{ number_format($stage['count']) }}<span class="op-share">{{ $stage['share'] }}%</span></span>
                    <span class="dw-track"><span class="dw-fill" style="display: block; width: {{ $stage['width'] }}%; background: {{ $stage['tint'] }};"></span></span>
                </a>
            @endforeach

            <div class="dw-foot">
                <div>
                    <span class="dw-kicker">To ship</span>
                    <b class="dw-num">{{ $daysToShip !== null ? $daysToShip.' days' : '—' }}</b>
                </div>
                <div>
                    <span class="dw-kicker">To deliver</span>
                    <b class="dw-num">{{ $daysToDeliver !== null ? $daysToDeliver.' days' : '—' }}</b>
                </div>
                <div>
                    <span class="dw-kicker">Cancelled</span>
                    <b class="dw-num">{{ number_format($cancelled) }}</b>
                </div>
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
