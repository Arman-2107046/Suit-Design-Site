{{-- One root element: Livewire attaches to the first, so the styles live inside it --}}
<x-filament-widgets::widget>
    @include('filament.widgets.partials.dashboard-styles')

    <x-filament::section heading="Customers" description="Where suits have shipped.">
        <x-slot name="afterHeader">
            <span class="dw-total dw-num">{{ number_format($totals['countries']) }} {{ $totals['countries'] === 1 ? 'country' : 'countries' }}</span>
        </x-slot>

        <style>
            .cm-wrap { display: grid; grid-template-columns: minmax(0, 1fr); gap: 18px; }
            @media (min-width: 1100px) { .cm-wrap.has-list { grid-template-columns: minmax(0, 1fr) 280px; } }
            /* A whole row to itself: tall enough that small countries can be told apart */
            .cm-map { position: relative; height: clamp(300px, 42vw, 520px); border-radius: 12px; background: linear-gradient(180deg, #fbfbfd, #f6f7fb); }
            .cm-map .jvm-tooltip {
                padding: 9px 11px; font-family: inherit; font-size: 12px; line-height: 1.45; color: #fff;
                background: #0f172a; border: 0; border-radius: 10px; box-shadow: 0 12px 28px -12px rgba(15, 23, 42, 0.6);
            }
            .cm-map .jvm-tooltip b { font-size: 13px; }
            .cm-loading { position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; font-size: 12px; color: #94a3b8; }
            .cm-list { display: flex; flex-direction: column; }
            .cm-row { display: grid; grid-template-columns: 22px minmax(0, 1fr) auto; align-items: center; gap: 2px 10px; padding: 9px 0; border-bottom: 1px solid #f1f5f9; }
            .cm-row:last-child { border-bottom: 0; }
            .cm-flag { font-size: 17px; line-height: 1; }
            .cm-name { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: 13px; font-weight: 600; }
            .cm-orders { font-size: 13px; font-weight: 600; text-align: right; }
            .cm-sub { grid-column: 2 / 4; font-size: 11.5px; color: #94a3b8; }
        </style>

        <div class="dw">
            @if ($countries->isEmpty())
                <div class="dw-empty">
                    <strong>No customers on the map yet</strong>
                    <p>Each order's shipping country will appear here.</p>
                </div>
            @else
                <div class="cm-wrap has-list">
                    {{-- wire:ignore keeps Livewire from redrawing over the map once it is built --}}
                    <div
                        class="cm-map"
                        wire:ignore
                        x-data="{
                            map: null,
                            markers: @js($markers),
                            assets: @js($assets),

                            /* Loaded once per page, however many times the dashboard is visited in SPA mode */
                            load(src, tag = 'script') {
                                window.__dashAssets ??= {};
                                return window.__dashAssets[src] ??= new Promise((resolve, reject) => {
                                    const el = document.createElement(tag === 'style' ? 'link' : 'script');
                                    if (tag === 'style') { el.rel = 'stylesheet'; el.href = src; } else { el.src = src; }
                                    el.onload = resolve;
                                    el.onerror = () => reject(new Error('Could not load ' + src));
                                    document.head.appendChild(el);
                                });
                            },

                            async init() {
                                try {
                                    await this.load(this.assets.style, 'style');
                                    await this.load(this.assets.script);
                                    await this.load(this.assets.map);
                                } catch (e) {
                                    this.$refs.loading.textContent = 'The map could not be loaded.';
                                    return;
                                }

                                this.$refs.loading.remove();
                                this.draw();
                            },

                            draw() {
                                const n = this.markers.length;
                                const bubble = (m, scale, opacity) => ({
                                    name: m.name,
                                    coords: m.coords,
                                    style: { initial: { r: m.radius * scale, fillOpacity: opacity }, hover: { fillOpacity: Math.min(1, opacity + 0.15) } },
                                });

                                /* Halos first, cores last, so no country's ring covers another's dot */
                                const all = [
                                    ...this.markers.map((m) => bubble(m, 2.1, 0.10)),
                                    ...this.markers.map((m) => bubble(m, 1.5, 0.20)),
                                    ...this.markers.map((m) => bubble(m, 1, 0.92)),
                                ];

                                const regionValues = Object.fromEntries(this.markers.map((m) => [m.code, m.orders]));
                                const byCode = Object.fromEntries(this.markers.map((m) => [m.code, m]));

                                const describe = (m) =>
                                    '<b>' + m.flag + ' ' + m.name + '</b><br>'
                                    + m.orders + (m.orders === 1 ? ' order' : ' orders') + ' · '
                                    + m.customers + (m.customers === 1 ? ' customer' : ' customers') + '<br>$'
                                    + Number(m.revenue).toLocaleString() + ' revenue';

                                this.map = new jsVectorMap({
                                    selector: this.$refs.canvas,
                                    map: 'world',
                                    backgroundColor: 'transparent',
                                    zoomButtons: false,
                                    zoomOnScroll: false,
                                    draggable: false,
                                    regionStyle: {
                                        initial: { fill: '#e2e8f0', stroke: '#ffffff', strokeWidth: 0.5, fillOpacity: 1 },
                                        hover: { fill: '#c7d2fe', cursor: 'default' },
                                    },
                                    markerStyle: {
                                        initial: { fill: '#4f46e5', stroke: '#ffffff', strokeWidth: 0 },
                                        hover: { fill: '#4338ca', cursor: 'default' },
                                    },
                                    markers: all,
                                    series: {
                                        regions: [{ attribute: 'fill', scale: ['#e0e7ff', '#a5b4fc'], values: regionValues, normalizeFunction: 'polynomial' }],
                                    },
                                    onMarkerTooltipShow: (event, tooltip, index) => {
                                        tooltip.text(describe(this.markers[Number(index) % n]), true);
                                    },
                                    onRegionTooltipShow: (event, tooltip, code) => {
                                        if (byCode[code]) tooltip.text(describe(byCode[code]), true);
                                    },
                                });
                            },

                            destroy() {
                                try { this.map?.destroy(); } catch (e) { /* already gone */ }
                            },
                        }"
                    >
                        <div x-ref="canvas" style="position: absolute; inset: 8px;"></div>
                        <div x-ref="loading" class="cm-loading">Loading map…</div>
                    </div>

                    <div class="cm-list">
                        <span class="dw-kicker" style="margin-bottom: 4px;">Top countries</span>
                        @foreach ($countries->take(6) as $country)
                            <div class="cm-row">
                                <span class="cm-flag">{{ $country['flag'] }}</span>
                                <span class="cm-name">{{ $country['name'] }}</span>
                                <span class="cm-orders dw-num">{{ number_format($country['orders']) }}</span>
                                <span class="cm-sub dw-num">{{ $country['customers'] }} {{ $country['customers'] === 1 ? 'customer' : 'customers' }} · ${{ number_format($country['revenue'], 0) }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
