{{-- What "Restore" will do: each field as it is now, and what it goes back to. --}}
@php
    $show = fn ($value) => match (true) {
        $value === null || $value === '' => null,
        is_bool($value) => $value ? 'Yes' : 'No',
        default => (string) $value,
    };
    $changedSince = collect($plan['fields'])->where('changed_since', true);
@endphp

<style>
    .rs { display: grid; gap: 14px; font-size: 13px; color: #334155; text-align: left; }
    .rs-warn { padding: 10px 12px; border-radius: 10px; background: #fffbeb; color: #92400e; box-shadow: inset 0 0 0 1px #fde68a; }
    .rs-card { border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; }
    .rs-table { width: 100%; border-collapse: collapse; table-layout: fixed; }
    .rs-table th { padding: 8px 12px; font-size: 10.5px; font-weight: 600; letter-spacing: 0.06em; text-transform: uppercase; color: #94a3b8; text-align: left; background: #f8fafc; border-bottom: 1px solid #e2e8f0; }
    .rs-table td { padding: 9px 12px; vertical-align: top; border-bottom: 1px solid #f1f5f9; word-break: break-word; }
    .rs-table tr:last-child td { border-bottom: 0; }
    .rs-field { width: 26%; font-weight: 600; color: #0f172a; }
    .rs-now { color: #64748b; text-decoration: line-through; text-decoration-color: rgb(100 116 139 / 0.4); }
    .rs-back { display: inline; padding: 1px 6px; border-radius: 6px; background: #ecfdf5; color: #047857; }
    .rs-none { color: #cbd5e1; }
    .rs-since { display: inline-block; margin-left: 6px; padding: 0 6px; border-radius: 999px; font-size: 10.5px; font-weight: 600; background: #fef3c7; color: #92400e; }
    .rs-skip { margin: 0; padding-left: 18px; color: #64748b; }
    .rs-skip li + li { margin-top: 4px; }
    .rs-skip b { color: #334155; }

    .dark .rs { color: #d4d4d8; }
    .dark .rs-warn { background: rgba(245, 158, 11, 0.1); color: #fde68a; box-shadow: inset 0 0 0 1px rgba(245, 158, 11, 0.3); }
    .dark .rs-card { border-color: rgba(255, 255, 255, 0.08); }
    .dark .rs-table th { background: #1f1f23; color: #71717a; border-bottom-color: rgba(255, 255, 255, 0.08); }
    .dark .rs-table td { border-bottom-color: rgba(255, 255, 255, 0.05); }
    .dark .rs-field, .dark .rs-skip b { color: #f4f4f5; }
    .dark .rs-now, .dark .rs-skip { color: #a1a1aa; }
    .dark .rs-back { background: rgba(16, 185, 129, 0.14); color: #6ee7b7; }
    .dark .rs-none { color: #52525b; }
    .dark .rs-since { background: rgba(245, 158, 11, 0.16); color: #fde68a; }
</style>

<div class="rs">
    @if ($changedSince->isNotEmpty())
        <div class="rs-warn">
            <strong>{{ $changedSince->pluck('label')->implode(', ') }}</strong>
            {{ $changedSince->count() === 1 ? 'has' : 'have' }} been edited again since. Restoring replaces those later changes too.
        </div>
    @endif

    @if ($plan['fields'])
        <div class="rs-card">
            <table class="rs-table">
                <thead>
                    <tr><th class="rs-field">Field</th><th>Now</th><th>Goes back to</th></tr>
                </thead>
                <tbody>
                    @foreach ($plan['fields'] as $field)
                        @php($now = $show($field['now']))
                        @php($back = $show($field['back_to']))
                        <tr>
                            <td class="rs-field">
                                {{ $field['label'] }}
                                @if ($field['changed_since'])<span class="rs-since">edited since</span>@endif
                            </td>
                            <td>@if ($now !== null)<span class="rs-now">{{ $now }}</span>@else<span class="rs-none">empty</span>@endif</td>
                            <td>@if ($back !== null)<span class="rs-back">{{ $back }}</span>@else<span class="rs-none">empty</span>@endif</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    @if ($plan['skipped'])
        <div>
            <p style="margin: 0 0 6px; font-weight: 600;">Left as they are</p>
            <ul class="rs-skip">
                @foreach ($plan['skipped'] as $skip)
                    <li><b>{{ $skip['label'] }}</b>: {{ $skip['why'] }}</li>
                @endforeach
            </ul>
        </div>
    @endif
</div>
