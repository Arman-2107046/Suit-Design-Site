{{--
    The assembled suit. Layers are stacked bottom first (see DesignPreview::layers()).
    Alpine keeps the view toggles; Livewire redraws when the design changes.
--}}
@php
    $types = array_column($layers, 'type');
@endphp

<style>
    .dp { border: 1px solid #e2e8f0; border-radius: 16px; background: #fff; overflow: hidden; }
    .dp-bar { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 10px; padding: 12px 16px; border-bottom: 1px solid #f1f5f9; }
    .dp-bar b { font-size: 13.5px; font-weight: 650; color: #0f172a; }
    .dp-seg { display: inline-flex; padding: 3px; border-radius: 10px; background: #f1f5f9; }
    .dp-seg button { padding: 4px 10px; border: 0; border-radius: 8px; background: none; font-size: 12px; font-weight: 600; color: #64748b; cursor: pointer; }
    .dp-seg button.on { background: #fff; color: #0f172a; box-shadow: 0 1px 2px rgba(15, 23, 42, 0.12); }

    .dp-stage { display: flex; justify-content: center; padding: 20px; min-height: 420px; transition: background-color 0.2s ease; }
    .dp-stage.bg-plain { background: linear-gradient(180deg, #fbfbfd, #f1f5f9); }
    .dp-stage.bg-grid { background-color: #fff; background-image: linear-gradient(45deg, #e2e8f0 25%, transparent 25%), linear-gradient(-45deg, #e2e8f0 25%, transparent 25%), linear-gradient(45deg, transparent 75%, #e2e8f0 75%), linear-gradient(-45deg, transparent 75%, #e2e8f0 75%); background-size: 20px 20px; background-position: 0 0, 0 10px, 10px -10px, -10px 0; }
    .dp-stage.bg-dark { background: #18181b; }
    .dp-suit { position: relative; width: 100%; max-width: 560px; }
    .dp-suit img { display: block; width: 100%; height: auto; }
    .dp-suit .dp-size { visibility: hidden; }
    .dp-suit .dp-layer { position: absolute; inset: 0; transition: opacity 0.2s ease; }
    .dp-empty { align-self: center; font-size: 13px; color: #94a3b8; }

    .dp-list { margin: 0; padding: 6px 0; list-style: none; border-top: 1px solid #f1f5f9; }
    .dp-row { display: grid; grid-template-columns: 26px minmax(0, 1fr) auto; align-items: center; gap: 12px; padding: 8px 16px; }
    .dp-row.focus { background: #eff6ff; box-shadow: inset 3px 0 0 #2563eb; }
    .dp-row.broken { background: #fef2f2; box-shadow: inset 3px 0 0 #dc2626; }
    .dp-z { display: inline-flex; align-items: center; justify-content: center; width: 26px; height: 26px; border-radius: 8px; background: #f1f5f9; font-size: 11px; font-weight: 650; color: #475569; font-variant-numeric: tabular-nums; }
    .dp-name { font-size: 13px; font-weight: 600; color: #0f172a; }
    .dp-sub { font-size: 12px; color: #64748b; }
    .dp-tag { margin-left: 6px; padding: 1px 7px; border-radius: 999px; font-size: 10.5px; font-weight: 600; }
    .dp-tag-hidden { background: #f1f5f9; color: #64748b; }
    .dp-tag-broken { background: #fee2e2; color: #b91c1c; }
    .dp-acts { display: flex; align-items: center; gap: 4px; }
    .dp-acts button, .dp-acts a { display: inline-flex; align-items: center; gap: 4px; padding: 4px 8px; border: 0; border-radius: 8px; background: none; font-size: 12px; font-weight: 600; color: #64748b; text-decoration: none; cursor: pointer; }
    .dp-acts button:hover, .dp-acts a:hover { background: #f1f5f9; color: #0f172a; }
    .dp-acts .on { color: #2563eb; }
    .dp-acts svg { width: 15px; height: 15px; }
    .dp-off .dp-name, .dp-off .dp-sub { opacity: 0.45; }

    .dark .dp { background: #18181b; border-color: rgba(255, 255, 255, 0.08); }
    .dark .dp-bar, .dark .dp-list { border-color: rgba(255, 255, 255, 0.06); }
    .dark .dp-bar b, .dark .dp-name { color: #f4f4f5; }
    .dark .dp-seg { background: rgba(255, 255, 255, 0.06); }
    .dark .dp-seg button { color: #a1a1aa; }
    .dark .dp-seg button.on { background: #27272a; color: #f4f4f5; }
    .dark .dp-stage.bg-plain { background: linear-gradient(180deg, #1f1f23, #18181b); }
    .dark .dp-stage.bg-grid { background-color: #27272a; background-image: linear-gradient(45deg, #3f3f46 25%, transparent 25%), linear-gradient(-45deg, #3f3f46 25%, transparent 25%), linear-gradient(45deg, transparent 75%, #3f3f46 75%), linear-gradient(-45deg, transparent 75%, #3f3f46 75%); }
    .dark .dp-z { background: rgba(255, 255, 255, 0.06); color: #a1a1aa; }
    .dark .dp-sub { color: #a1a1aa; }
    .dark .dp-row.focus { background: rgba(59, 130, 246, 0.12); }
    .dark .dp-row.broken { background: rgba(239, 68, 68, 0.12); }
    .dark .dp-tag-hidden { background: rgba(255, 255, 255, 0.06); color: #a1a1aa; }
    .dark .dp-tag-broken { background: rgba(239, 68, 68, 0.16); color: #fca5a5; }
    .dark .dp-acts button:hover, .dark .dp-acts a:hover { background: rgba(255, 255, 255, 0.06); color: #f4f4f5; }
    .dark .dp-acts .on { color: #60a5fa; }
</style>

<div
    class="dp"
    wire:key="design-preview-{{ md5(json_encode(array_column($layers, 'raw'))) }}"
    x-data="{
        bg: 'plain',
        off: {},
        solo: null,
        broken: {},
        shown(type) { return this.solo ? this.solo === type : ! this.off[type]; },
    }"
>
    <div class="dp-bar">
        <b>{{ count($layers) }} {{ str('layer')->plural(count($layers)) }}, bottom to top</b>
        <div class="dp-seg" role="group" aria-label="Background">
            <button type="button" x-bind:class="{ on: bg === 'plain' }" x-on:click="bg = 'plain'">Plain</button>
            <button type="button" x-bind:class="{ on: bg === 'grid' }" x-on:click="bg = 'grid'" title="Shows where each picture is transparent">Transparency</button>
            <button type="button" x-bind:class="{ on: bg === 'dark' }" x-on:click="bg = 'dark'" title="Edges and halos show up against dark">Dark</button>
        </div>
    </div>

    <div class="dp-stage" x-bind:class="'bg-' + bg">
        @if ($layers === [])
            <p class="dp-empty">Pick a fabric with at least a body to see the suit.</p>
        @else
            <div class="dp-suit">
                <img class="dp-size" src="{{ $layers[0]['image'] }}" alt="">
                @foreach ($layers as $i => $layer)
                    <img
                        class="dp-layer"
                        src="{{ $layer['image'] }}"
                        alt="{{ $layer['label'] }}"
                        style="z-index: {{ $i + 1 }};"
                        x-bind:style="{ opacity: shown(@js($layer['type'])) ? 1 : 0 }"
                        x-on:error="broken[@js($layer['type'])] = true"
                    >
                @endforeach
            </div>
        @endif
    </div>

    @if ($layers !== [])
        <ul class="dp-list">
            {{-- Top of the stack first, as you would read a layers panel --}}
            @foreach (array_reverse($layers) as $layer)
                <li
                    class="dp-row {{ $focus === $layer['type'] ? 'focus' : '' }}"
                    x-bind:class="{ broken: broken[@js($layer['type'])], 'dp-off': ! shown(@js($layer['type'])) }"
                >
                    <span class="dp-z" title="Layer index">{{ $layer['z'] }}</span>
                    <div style="min-width: 0;">
                        <div class="dp-name">
                            {{ $layer['label'] }}
                            @if ($layer['hidden'])<span class="dp-tag dp-tag-hidden">Hidden from customers</span>@endif
                            <span class="dp-tag dp-tag-broken" x-show="broken[@js($layer['type'])]" x-cloak>Picture does not load</span>
                        </div>
                        <div class="dp-sub">{{ $layer['name'] }}</div>
                    </div>
                    <div class="dp-acts">
                        <button type="button" x-on:click="off[@js($layer['type'])] = ! off[@js($layer['type'])]; solo = null" x-bind:title="off[@js($layer['type'])] ? 'Show this layer' : 'Hide this layer'">
                            {{-- Wrapped: Filament's icon does not pass x-show on to the svg --}}
                            <span x-show="! off[@js($layer['type'])]" style="display: inline-flex;"><x-filament::icon icon="heroicon-m-eye" /></span>
                            <span x-show="off[@js($layer['type'])]" x-cloak style="display: inline-flex;"><x-filament::icon icon="heroicon-m-eye-slash" /></span>
                        </button>
                        <button type="button" x-bind:class="{ on: solo === @js($layer['type']) }" x-on:click="solo = solo === @js($layer['type']) ? null : @js($layer['type'])" title="Show only this layer">Solo</button>
                        @if ($layer['edit'])
                            <a href="{{ $layer['edit'] }}" title="Open this layer to replace its picture">Edit</a>
                        @endif
                    </div>
                </li>
            @endforeach
        </ul>
    @endif
</div>
