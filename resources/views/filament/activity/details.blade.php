@php
    /** @var \App\Models\Activity $activity */
    use Illuminate\Support\Str;

    $name = $activity->admin?->name ?? $activity->admin_name;
    $changes = $activity->changes();
    $properties = $activity->properties ?? [];

    /* A link to the record itself, while it still exists and has a page in the admin. */
    $recordUrl = null;
    if ($activity->subject_type && $activity->subject_id && $activity->event !== 'deleted') {
        $resource = \Filament\Facades\Filament::getModelResource($activity->subject_type);
        $record = $resource ? $activity->subject_type::find($activity->subject_id) : null;
        if ($record) {
            foreach (['view', 'edit'] as $page) {
                if ($resource::hasPage($page) && $resource::can($page === 'view' ? 'view' : 'update', $record)) {
                    $recordUrl = $resource::getUrl($page, ['record' => $record]);
                    break;
                }
            }
        }
    }

    $show = function ($value) {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        return (string) $value;
    };

    $device = $activity->device();
@endphp

<style>
    .al { display: grid; gap: 20px; font-size: 13.5px; color: #334155; }
    .al-card { border: 1px solid #e2e8f0; border-radius: 14px; background: #fff; overflow: hidden; }
    .al-head { display: flex; align-items: center; gap: 14px; padding: 16px 18px; background: linear-gradient(180deg, #f8fafc, #fff); }
    .al-meta { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 1px; background: #f1f5f9; border-top: 1px solid #f1f5f9; }
    .al-meta div { padding: 10px 18px; background: #fff; min-width: 0; }
    .al-meta dt { font-size: 10.5px; font-weight: 600; letter-spacing: 0.06em; text-transform: uppercase; color: #94a3b8; }
    .al-meta dd { margin: 2px 0 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; color: #0f172a; }
    .al-title { font-size: 11px; font-weight: 700; letter-spacing: 0.07em; text-transform: uppercase; color: #64748b; margin: 0 0 8px; }
    .al-table { width: 100%; border-collapse: collapse; table-layout: fixed; }
    .al-table th { padding: 9px 14px; font-size: 10.5px; font-weight: 600; letter-spacing: 0.06em; text-transform: uppercase; color: #94a3b8; text-align: left; background: #f8fafc; border-bottom: 1px solid #e2e8f0; }
    .al-table td { padding: 10px 14px; vertical-align: top; border-bottom: 1px solid #f1f5f9; word-break: break-word; }
    .al-table tr:last-child td { border-bottom: 0; }
    .al-field { width: 30%; font-weight: 600; color: #0f172a; }
    .al-old { display: inline; padding: 1px 6px; border-radius: 6px; background: #fef2f2; color: #b91c1c; text-decoration: line-through; text-decoration-color: rgb(185 28 28 / 0.35); }
    .al-new { display: inline; padding: 1px 6px; border-radius: 6px; background: #ecfdf5; color: #047857; }
    .al-none { color: #cbd5e1; }
    .al-link { display: inline-flex; align-items: center; gap: 6px; font-weight: 600; color: #4f46e5; text-decoration: none; }
    .al-link:hover { text-decoration: underline; }
    .al-who { font-size: 15px; font-weight: 650; color: #0f172a; }
    .al-when { font-size: 12.5px; color: #64748b; }
    .al-faint { color: #94a3b8; }
    .al-dest { font-weight: 600; color: #0f172a; }
    .al-num { font-variant-numeric: tabular-nums; color: #475569; }
    .al-warncard { padding: 14px 18px; background: #fffbeb; border-color: #fde68a; color: #92400e; }
    .al-bar { height: 6px; border-radius: 9999px; background: #eef2ff; overflow: hidden; }
    .al-bar span { display: block; height: 100%; border-radius: inherit; background: linear-gradient(90deg, #6366f1, #06b6d4); }
</style>

<div class="al">
    <div class="al-card">
        <div class="al-head">
            @include('filament.partials.admin-avatar', ['name' => $name, 'initials' => \App\Models\Admin::initialsFor($name), 'size' => 42])
            <div style="min-width: 0; flex: 1;">
                <div class="al-who">{{ $name ?? 'Unknown' }}</div>
                <div class="al-when">
                    {{ $activity->created_at->diffForHumans() }}
                    @if ($name && ! $activity->admin) · no longer an administrator @endif
                </div>
            </div>
            <x-filament::badge :color="$activity->eventColor()" :icon="$activity->eventIcon()">
                {{ $activity->eventLabel() }}
            </x-filament::badge>
        </div>

        <dl class="al-meta" style="margin: 0;">
            <div>
                <dt>Record</dt>
                <dd>
                    @if ($recordUrl)
                        <a class="al-link" href="{{ $recordUrl }}">{{ $activity->subjectTypeLabel() }} #{{ $activity->subject_id }} →</a>
                    @elseif ($activity->subject_type)
                        {{ $activity->subjectTypeLabel() }}{{ $activity->subject_id ? ' #'.$activity->subject_id : '' }}
                        @if ($activity->event === 'deleted') <span class="al-faint">(deleted)</span> @endif
                    @else
                        <span class="al-none">—</span>
                    @endif
                </dd>
            </div>
            <div>
                <dt>When</dt>
                <dd>{{ $activity->created_at->format('j M Y, H:i:s') }}</dd>
            </div>
            <div>
                <dt>IP address</dt>
                <dd style="font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 12.5px;">{{ $activity->ip ?? '—' }}</dd>
            </div>
            <div>
                <dt>Device</dt>
                <dd title="{{ $activity->user_agent }}">{{ $device ?: '—' }}</dd>
            </div>
        </dl>
    </div>

    @if ($activity->event === 'bulk_upload')
        @php($breakdown = $properties['breakdown'] ?? [])
        @php($top = max(1, ...array_values($breakdown ?: [0])))
        <div>
            <p class="al-title">Filed {{ number_format($properties['filed'] ?? 0) }} {{ Str::plural('file', $properties['filed'] ?? 0) }}@if (($properties['failed'] ?? 0) > 0), {{ $properties['failed'] }} rejected @endif</p>
            <div class="al-card" style="padding: 14px 18px; display: grid; gap: 12px;">
                @forelse ($breakdown as $destination => $count)
                    <div>
                        <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                            <span class="al-dest">{{ Str::headline($destination) }}</span>
                            <span class="al-num">{{ number_format($count) }}</span>
                        </div>
                        <div class="al-bar"><span style="width: {{ round($count / $top * 100, 1) }}%;"></span></div>
                    </div>
                @empty
                    <span class="al-none">Nothing was filed.</span>
                @endforelse
            </div>
        </div>
    @elseif ($activity->event === 'login_failed')
        <div class="al-card al-warncard">
            Someone tried to sign in as <strong>{{ $activity->subject_label }}</strong> with the wrong password
            @if (! $activity->subject_id) — no administrator has that email @endif.
        </div>
    @elseif ($activity->event === 'reordered')
        <div class="al-card" style="padding: 14px 18px;">
            Dragged {{ strtolower(Str::plural($activity->subjectTypeLabel() ?? 'item')) }} into a new order ({{ $properties['count'] ?? '?' }} positions saved).
        </div>
    @elseif ($changes !== [])
        <div>
            <p class="al-title">
                {{ match ($activity->event) { 'updated' => 'What changed', 'deleted' => 'What was removed', default => 'What was saved' } }}
            </p>
            <div class="al-card">
                <table class="al-table">
                    <thead>
                        <tr>
                            <th class="al-field">Field</th>
                            @if ($activity->event === 'updated')
                                <th>Before</th>
                                <th>After</th>
                            @else
                                <th>Value</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($changes as $change)
                            @php($old = $show($change['old']))
                            @php($new = $show($change['new']))
                            <tr>
                                <td class="al-field">{{ Str::headline($change['field']) }}</td>
                                @if ($activity->event === 'updated')
                                    <td>@if ($old !== null)<span class="al-old">{{ $old }}</span>@else<span class="al-none">empty</span>@endif</td>
                                    <td>@if ($new !== null)<span class="al-new">{{ $new }}</span>@else<span class="al-none">empty</span>@endif</td>
                                @else
                                    @php($value = $activity->event === 'deleted' ? $old : $new)
                                    <td>@if ($value !== null){{ $value }}@else<span class="al-none">—</span>@endif</td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
