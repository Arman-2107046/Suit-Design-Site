{{-- One root element: Livewire attaches to the first, so the styles live inside it --}}
<x-filament-widgets::widget wire:poll.30s>
    @include('filament.widgets.partials.dashboard-styles')

    <x-filament::section heading="Recent activity" description="What the team has been doing in the admin.">
        <x-slot name="afterHeader">
            <x-filament::link :href="$logUrl" icon="heroicon-m-arrow-right" icon-position="after" size="sm">
                Full activity log
            </x-filament::link>
        </x-slot>

        <style>
            .ra-list { margin: 0; padding: 0; list-style: none; }
            .ra-item { position: relative; display: grid; grid-template-columns: 30px minmax(0, 1fr) auto; align-items: center; gap: 12px; padding: 9px 0; }
            /* The thread that joins one entry to the next */
            .ra-item:not(:last-child)::before { content: ""; position: absolute; left: 14.5px; top: 40px; bottom: -8px; width: 1px; background: #e2e8f0; }
            .ra-icon { position: relative; z-index: 1; display: flex; align-items: center; justify-content: center; width: 30px; height: 30px; border-radius: 9999px; }
            .ra-icon svg { width: 15px; height: 15px; }
            .ra-text { min-width: 0; font-size: 13px; line-height: 1.35; color: #334155; }
            .ra-text b { font-weight: 600; color: #0f172a; }
            .ra-text span { display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
            .ra-time { font-size: 12px; color: #94a3b8; white-space: nowrap; }

            .ra-tone-success { background: #ecfdf5; color: #059669; }
            .ra-tone-info { background: #eff6ff; color: #2563eb; }
            .ra-tone-danger { background: #fef2f2; color: #dc2626; }
            .ra-tone-primary { background: #eef2ff; color: #4f46e5; }
            .ra-tone-warning { background: #fffbeb; color: #d97706; }
            .ra-tone-gray { background: #f1f5f9; color: #64748b; }
        </style>

        <div class="dw">
            @if ($activities->isEmpty())
                <div class="dw-empty">
                    <strong>Nothing yet</strong>
                    <p>Sign-ins, edits and uploads by the team will show up here.</p>
                </div>
            @else
                <ul class="ra-list">
                    @foreach ($activities as $activity)
                        <li class="ra-item">
                            <span class="ra-icon ra-tone-{{ $activity->eventColor() }}">
                                <x-filament::icon :icon="$activity->eventIcon()" />
                            </span>
                            <div class="ra-text">
                                <span><b>{{ $activity->admin?->name ?? $activity->admin_name ?? 'Someone' }}</b> · {{ $activity->sentence() }}</span>
                            </div>
                            <time class="ra-time" datetime="{{ $activity->created_at->toIso8601String() }}" title="{{ $activity->created_at->format('j M Y, H:i') }}">
                                {{ $activity->created_at->diffForHumans(short: true) }}
                            </time>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
