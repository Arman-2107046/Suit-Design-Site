{{-- One root element: Livewire attaches to the first, so the styles live inside it --}}
<x-filament-widgets::widget>
    @include('filament.widgets.partials.dashboard-styles')

    <x-filament::section heading="Design trends" description="What customers choose, from every suit ordered.">
        <x-slot name="afterHeader">
            <span class="dw-total dw-num">{{ number_format($suits) }} {{ $suits === 1 ? 'suit' : 'suits' }}</span>
        </x-slot>

        <style>
            .dt-grid { display: grid; grid-template-columns: minmax(0, 1fr); gap: 12px; }
            @media (min-width: 700px) { .dt-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
            @media (min-width: 1280px) { .dt-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); } }
            .dt-card { padding: 14px 16px 16px; background: #fbfbfd; border: 1px solid #f1f5f9; border-radius: 12px; }
            .dt-card .dw-kicker { display: block; margin-bottom: 12px; }
            .dt-body { display: flex; align-items: center; gap: 16px; }

            /* The donut: a slim conic-gradient ring with the card's colour punched through the middle */
            .dt-donut {
                position: relative; flex-shrink: 0; width: 88px; height: 88px; border-radius: 50%;
                animation: dt-in 0.9s cubic-bezier(0.16, 1, 0.3, 1) both;
            }
            .dt-donut::after { content: ""; position: absolute; inset: 8px; border-radius: 50%; background: #fbfbfd; }
            .dt-centre { position: absolute; inset: 0; z-index: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; line-height: 1; }
            .dt-centre b { font-size: 17px; font-weight: 700; letter-spacing: -0.02em; }
            .dt-centre span { margin-top: 3px; font-size: 9px; font-weight: 600; letter-spacing: 0.06em; text-transform: uppercase; color: #94a3b8; }
            .dt-empty-ring { background: #eef2f7; }

            .dt-legend { flex: 1; min-width: 0; margin: 0; padding: 0; list-style: none; }
            .dt-legend li { display: flex; align-items: center; gap: 8px; padding: 3px 0; }
            .dt-dot { width: 8px; height: 8px; flex-shrink: 0; border-radius: 3px; }
            .dt-name { flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: 12.5px; color: #334155; }
            .dt-legend li:first-child .dt-name { font-weight: 600; color: #0f172a; }
            .dt-pct { flex-shrink: 0; font-size: 12px; font-weight: 600; color: #475569; }

            @keyframes dt-in {
                from { opacity: 0; transform: rotate(-110deg) scale(0.86); }
                to { opacity: 1; transform: none; }
            }
            @media (prefers-reduced-motion: reduce) { .dt-donut { animation: none; } }
        </style>

        <div class="dw">
            @if ($suits === 0)
                <div class="dw-empty">
                    <strong>No designs yet</strong>
                    <p>The first orders will show which styles customers reach for.</p>
                </div>
            @else
                <div class="dt-grid">
                    @foreach ($parts as $part => $donut)
                        <div class="dt-card">
                            <span class="dw-kicker">{{ $part }}</span>

                            <div class="dt-body">
                                @if ($donut['gradient'])
                                    <div class="dt-donut" style="background: {{ $donut['gradient'] }};" role="img" aria-label="{{ $part }}: {{ $donut['label'] }}">
                                        <div class="dt-centre">
                                            <b class="dw-num">{{ round($donut['lead']['share']) }}%</b>
                                            <span>top pick</span>
                                        </div>
                                    </div>

                                    <ul class="dt-legend">
                                        @foreach ($donut['slices'] as $slice)
                                            <li>
                                                <span class="dt-dot" style="background: {{ $slice['color'] }};"></span>
                                                <span class="dt-name" title="{{ $slice['name'] }}">{{ $slice['name'] }}</span>
                                                <span class="dt-pct dw-num">{{ round($slice['share']) }}%</span>
                                            </li>
                                        @endforeach
                                    </ul>
                                @else
                                    <div class="dt-donut dt-empty-ring" aria-hidden="true"></div>
                                    <p class="dw-faint" style="margin: 0; font-size: 12px;">Not recorded yet</p>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
