{{-- One root element: Livewire attaches to the first, so the styles live inside it --}}
<x-filament-widgets::widget>
    @include('filament.widgets.partials.dashboard-styles')

    <x-filament::section heading="Most ordered fabrics" description="By suits made, all time.">
        <style>
            .tf-row { display: flex; align-items: center; gap: 12px; padding: 9px 8px; margin: 0 -8px; border-radius: 10px; color: inherit; text-decoration: none; transition: background 0.15s ease; }
            a.tf-row:hover { background: #f8fafc; }
            .tf-rank { width: 16px; flex-shrink: 0; font-size: 12px; font-weight: 600; color: #cbd5e1; text-align: center; }
            .tf-swatch { width: 44px; height: 44px; flex-shrink: 0; overflow: hidden; border-radius: 10px; background: #efece6; box-shadow: inset 0 0 0 1px rgba(15, 23, 42, 0.06); }
            .tf-swatch img { display: block; width: 100%; height: 100%; object-fit: cover; }
            .tf-body { flex: 1; min-width: 0; }
            .tf-name { display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: 13.5px; font-weight: 600; color: #0f172a; }
            .tf-meta { display: block; margin-top: 2px; font-size: 11.5px; color: #94a3b8; }
            .tf-body .dw-track { height: 4px; margin-top: 7px; }
            .tf-count { flex-shrink: 0; text-align: right; }
            .tf-count b { display: block; font-size: 15px; font-weight: 600; }
            .tf-count span { font-size: 11px; color: #94a3b8; }
        </style>

        <div class="dw">
            @forelse ($fabrics as $i => $fabric)
                @php($tag = $fabric['url'] ? 'a' : 'div')
                <{{ $tag }} @if ($fabric['url']) href="{{ $fabric['url'] }}" title="Open {{ $fabric['name'] }}" @endif class="tf-row">
                    <span class="tf-rank dw-num">{{ $i + 1 }}</span>
                    <span class="tf-swatch">
                        @if ($fabric['thumb'])
                            <img src="{{ $fabric['thumb'] }}" alt="" loading="lazy" onerror="this.remove()">
                        @endif
                    </span>
                    <span class="tf-body">
                        <span class="tf-name">{{ $fabric['name'] }}</span>
                        <span class="tf-meta dw-num">
                            @if ($fabric['fabric_id'])#{{ $fabric['fabric_id'] }} · @endif${{ number_format($fabric['revenue'], 0) }} revenue
                        </span>
                        <span class="dw-track" style="display: block;"><span class="dw-fill" style="display: block; width: {{ $fabric['share'] }}%; background: #4f46e5;"></span></span>
                    </span>
                    <span class="tf-count">
                        <b class="dw-num">{{ number_format($fabric['suits']) }}</b>
                        <span>{{ $fabric['suits'] === 1 ? 'suit' : 'suits' }}</span>
                    </span>
                </{{ $tag }}>
            @empty
                <div class="dw-empty">
                    <strong>No orders yet</strong>
                    <p>The fabrics customers choose will rank here.</p>
                </div>
            @endforelse
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
