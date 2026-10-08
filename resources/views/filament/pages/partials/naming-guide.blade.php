{{--
    Every filename rule of the bulk uploader (App\Services\BulkUpload\NamingGuide),
    shown inside a collapsed section on the Bulk Upload page.
--}}
<style>
    .ng { display: grid; gap: 22px; }
    .ng-general { margin: 0; padding: 14px 18px 14px 34px; list-style: disc; border-radius: 12px; background: #f8fafc; box-shadow: inset 0 0 0 1px #e2e8f0; font-size: 13px; line-height: 1.65; color: #475569; }
    .ng-general li + li { margin-top: 2px; }
    .ng-group-title { margin: 0; font-size: 12px; font-weight: 600; letter-spacing: 0.08em; text-transform: uppercase; color: #64748b; }
    .ng-group-intro { margin: 3px 0 10px; font-size: 13px; color: #64748b; }
    .ng-rule { display: grid; grid-template-columns: 64px minmax(0, 1fr); gap: 4px 16px; padding: 14px 0; border-top: 1px solid #e2e8f0; }
    .ng-rule:first-of-type { border-top: 0; }
    .ng-prefix { align-self: start; justify-self: start; padding: 4px 9px; border-radius: 8px; background: var(--primary-50); color: var(--primary-700); font: 600 13px/1 ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; }
    .ng-makes { font-size: 14px; font-weight: 600; color: #0f172a; }
    .ng-code { display: inline-block; margin-top: 6px; padding: 5px 9px; border-radius: 7px; background: #f1f5f9; font: 12.5px/1.4 ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; color: #0f172a; word-break: break-all; }
    .ng-example { margin-top: 6px; font-size: 12.5px; color: #64748b; }
    .ng-example code { font: 12.5px ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; color: var(--primary-700); }
    .ng-parts { margin: 8px 0 0; padding: 0; list-style: none; font-size: 12.5px; color: #475569; }
    .ng-parts li { display: grid; grid-template-columns: 110px minmax(0, 1fr); gap: 10px; padding: 2px 0; }
    .ng-parts b { font-weight: 600; color: #0f172a; }
    .ng-meta { margin-top: 8px; font-size: 12.5px; color: #64748b; }
    .ng-meta b { font-weight: 600; color: #475569; }

    .dark .ng-general { background: rgba(255, 255, 255, 0.04); box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.08); color: #a1a1aa; }
    .dark .ng-group-title, .dark .ng-group-intro, .dark .ng-example, .dark .ng-meta { color: #a1a1aa; }
    .dark .ng-meta b, .dark .ng-parts { color: #d4d4d8; }
    .dark .ng-rule { border-top-color: rgba(255, 255, 255, 0.08); }
    .dark .ng-prefix { background: rgba(255, 255, 255, 0.08); color: var(--primary-200); }
    .dark .ng-makes, .dark .ng-parts b, .dark .ng-code { color: #fafafa; }
    .dark .ng-code { background: rgba(255, 255, 255, 0.07); }
    .dark .ng-example code { color: var(--primary-300); }

    @media (max-width: 640px) {
        .ng-rule { grid-template-columns: minmax(0, 1fr); }
        .ng-parts li { grid-template-columns: minmax(0, 1fr); }
    }
</style>

<div class="ng">
    <div>
        <p class="ng-group-title">Rules for every filename</p>
        <ul class="ng-general" style="margin-top: 8px;">
            @foreach (\App\Services\BulkUpload\NamingGuide::general() as $line)
                <li>{{ $line }}</li>
            @endforeach
        </ul>
    </div>

    @foreach (\App\Services\BulkUpload\NamingGuide::groups() as $group)
        <div>
            <p class="ng-group-title">{{ $group['title'] }}</p>
            <p class="ng-group-intro">{{ $group['intro'] }}</p>

            @foreach ($group['rules'] as $rule)
                <div class="ng-rule">
                    <span class="ng-prefix">{{ $rule['prefix'] }}</span>
                    <div>
                        <div class="ng-makes">{{ $rule['makes'] }}</div>
                        <div class="ng-code">{{ $rule['pattern'] }}</div>
                        <div class="ng-example">For example <code>{{ $rule['example'] }}</code></div>

                        <ul class="ng-parts">
                            @foreach ($rule['parts'] as [$part, $meaning])
                                <li><b>{{ $part }}</b><span>{{ $meaning }}</span></li>
                            @endforeach
                        </ul>

                        @if ($rule['needs'])
                            <div class="ng-meta"><b>Needs first:</b> {{ $rule['needs'] }}</div>
                        @endif

                        @foreach ($rule['notes'] as $note)
                            <div class="ng-meta">{{ $note }}</div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    @endforeach
</div>
