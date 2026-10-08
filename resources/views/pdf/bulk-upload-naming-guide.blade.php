{{-- Dompdf has no flexbox or grid, so the guide is laid out with tables. --}}
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Bulk upload: naming conventions</title>
    <style>
        @page { margin: 30mm 16mm 20mm; }

        body { font-family: 'DejaVu Sans', sans-serif; font-size: 9.5px; line-height: 1.5; color: #1f2937; }

        header { position: fixed; top: -21mm; left: 0; right: 0; height: 16mm; }
        .brand { font-size: 9px; font-weight: bold; letter-spacing: 3px; text-transform: uppercase; color: #1e3a6e; }
        .rule-line { border-bottom: 1px solid #e5e7eb; margin-top: 5px; }

        footer { position: fixed; bottom: -12mm; left: 0; right: 0; font-size: 8px; color: #9ca3af; }
        footer .page:after { content: counter(page); }

        h1 { margin: 0; font-size: 20px; font-weight: normal; letter-spacing: -0.4px; color: #0f172a; }
        .meta { margin: 4px 0 0; font-size: 9px; color: #9ca3af; }

        h2 { margin: 22px 0 2px; font-size: 11px; font-weight: bold; letter-spacing: 1.5px; text-transform: uppercase; color: #1e3a6e; }
        .intro { margin: 0 0 8px; font-size: 9.5px; color: #6b7280; }

        ul.general { margin: 12px 0 0; padding: 10px 14px 10px 28px; background: #f8fafc; border: 1px solid #e2e8f0; }
        ul.general li { margin: 1px 0; }

        table.rule { width: 100%; border-collapse: collapse; page-break-inside: avoid; }
        table.rule td { padding: 9px 0; border-top: 1px solid #e5e7eb; vertical-align: top; }
        td.prefix { width: 52px; }
        .badge { display: inline-block; padding: 3px 6px; background: #e8edf7; color: #1e3a6e; font-family: 'DejaVu Sans Mono', monospace; font-size: 10px; font-weight: bold; }
        .makes { font-size: 10.5px; font-weight: bold; color: #0f172a; }
        .pattern { margin-top: 3px; padding: 3px 6px; background: #f1f5f9; font-family: 'DejaVu Sans Mono', monospace; font-size: 9px; color: #0f172a; }
        .example { margin-top: 3px; color: #6b7280; }
        .example span { font-family: 'DejaVu Sans Mono', monospace; color: #1e3a6e; }
        table.parts { margin-top: 4px; border-collapse: collapse; }
        table.parts td { padding: 1px 10px 1px 0; border: 0; vertical-align: top; color: #4b5563; }
        table.parts td.part { width: 78px; font-weight: bold; color: #0f172a; }
        .note { margin-top: 3px; color: #6b7280; }
        .note b { color: #4b5563; }
    </style>
</head>
<body>
    <header>
        <div class="brand">Custom Tailor</div>
        <div class="rule-line"></div>
    </header>

    <footer>
        Bulk upload: naming conventions &middot; page <span class="page"></span>
    </footer>

    <h1>Naming conventions for bulk upload</h1>
    <p class="meta">
        Generated {{ $generatedAt->format('j M Y, H:i') }}@if ($admin) &middot; {{ $admin }}@endif
        &middot; The prefix of the filename decides where each picture goes.
    </p>

    <ul class="general">
        @foreach ($general as $line)
            <li>{{ $line }}</li>
        @endforeach
    </ul>

    @foreach ($groups as $group)
        <h2>{{ $group['title'] }}</h2>
        <p class="intro">{{ $group['intro'] }}</p>

        @foreach ($group['rules'] as $rule)
            <table class="rule">
                <tr>
                    <td class="prefix"><span class="badge">{{ $rule['prefix'] }}</span></td>
                    <td>
                        <div class="makes">{{ $rule['makes'] }}</div>
                        <div class="pattern">{{ $rule['pattern'] }}</div>
                        <div class="example">For example <span>{{ $rule['example'] }}</span></div>

                        <table class="parts">
                            @foreach ($rule['parts'] as [$part, $meaning])
                                <tr><td class="part">{{ $part }}</td><td>{{ $meaning }}</td></tr>
                            @endforeach
                        </table>

                        @if ($rule['needs'])
                            <div class="note"><b>Needs first:</b> {{ $rule['needs'] }}</div>
                        @endif

                        @foreach ($rule['notes'] as $note)
                            <div class="note">{{ $note }}</div>
                        @endforeach
                    </td>
                </tr>
            </table>
        @endforeach
    @endforeach
</body>
</html>
