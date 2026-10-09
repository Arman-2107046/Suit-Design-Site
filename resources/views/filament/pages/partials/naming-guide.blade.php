{{--
    The bulk uploader's filename rules, kept short (App\Services\BulkUpload\NamingGuide),
    shown inside a collapsed section on the Bulk Upload page.
--}}
<style>
    .ng-general { margin: 0 0 14px; padding: 0 0 0 18px; list-style: disc; font-size: 13px; line-height: 1.6; color: #475569; }
    .ng-table { width: 100%; border-collapse: collapse; font-size: 13px; }
    .ng-table th { padding: 14px 12px 6px 0; text-align: left; font-size: 11px; font-weight: 600; letter-spacing: 0.08em; text-transform: uppercase; color: #64748b; }
    .ng-table td { padding: 7px 12px 7px 0; border-top: 1px solid #e2e8f0; vertical-align: top; color: #475569; }
    .ng-table .ng-prefix { width: 56px; font: 600 12.5px ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; color: var(--primary-700); }
    .ng-table .ng-makes { width: 150px; font-weight: 600; color: #0f172a; }
    .ng-table code { font: 12.5px ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; word-break: break-all; }
    .ng-table .ng-pattern code { color: #0f172a; }
    .ng-table .ng-example code { color: var(--primary-700); }

    .dark .ng-general, .dark .ng-table td { color: #a1a1aa; }
    .dark .ng-table th { color: #a1a1aa; }
    .dark .ng-table td { border-top-color: rgba(255, 255, 255, 0.08); }
    .dark .ng-table .ng-prefix, .dark .ng-table .ng-example code { color: var(--primary-300); }
    .dark .ng-table .ng-makes, .dark .ng-table .ng-pattern code { color: #fafafa; }

    @media (max-width: 760px) {
        .ng-table .ng-makes { width: auto; }
        .ng-table .ng-example { display: none; }
    }
</style>

<ul class="ng-general">
    @foreach (\App\Services\BulkUpload\NamingGuide::general() as $line)
        <li>{{ $line }}</li>
    @endforeach
</ul>

<table class="ng-table">
    @foreach (\App\Services\BulkUpload\NamingGuide::groups() as $group)
        <tr><th colspan="4">{{ $group['title'] }}</th></tr>

        @foreach ($group['rules'] as $rule)
            <tr>
                <td class="ng-prefix">{{ $rule['prefix'] }}</td>
                <td class="ng-makes">{{ $rule['makes'] }}</td>
                <td class="ng-pattern"><code>{{ $rule['pattern'] }}</code></td>
                <td class="ng-example"><code>{{ $rule['example'] }}</code></td>
            </tr>
        @endforeach
    @endforeach
</table>
