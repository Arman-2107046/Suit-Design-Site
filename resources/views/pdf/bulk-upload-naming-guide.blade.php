{{-- Dompdf has no flexbox or grid, so the guide is laid out with a table. --}}
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Bulk upload: naming conventions</title>
    <style>
        @page { margin: 22mm 16mm 16mm; }

        body { font-family: 'DejaVu Sans', sans-serif; font-size: 9px; line-height: 1.45; color: #1f2937; }

        .brand { font-size: 8.5px; font-weight: bold; letter-spacing: 3px; text-transform: uppercase; color: #1e3a6e; }
        h1 { margin: 6px 0 0; font-size: 19px; font-weight: normal; letter-spacing: -0.4px; color: #0f172a; }
        .meta { margin: 3px 0 0; font-size: 8.5px; color: #9ca3af; }

        ul.general { margin: 12px 0 4px; padding: 0 0 0 16px; color: #4b5563; }

        table { width: 100%; border-collapse: collapse; }
        th { padding: 14px 0 4px; text-align: left; font-size: 8.5px; letter-spacing: 1.5px; text-transform: uppercase; color: #1e3a6e; }
        td { padding: 4px 8px 4px 0; border-top: 1px solid #e5e7eb; vertical-align: top; }
        td.prefix { width: 34px; font-family: 'DejaVu Sans Mono', monospace; font-weight: bold; color: #1e3a6e; }
        td.makes { width: 95px; font-weight: bold; color: #0f172a; }
        td.pattern { width: 180px; font-family: 'DejaVu Sans Mono', monospace; font-size: 8px; color: #0f172a; }
        td.example { font-family: 'DejaVu Sans Mono', monospace; font-size: 8px; color: #1e3a6e; }
    </style>
</head>
<body>
    <div class="brand">Custom Tailor</div>
    <h1>Naming conventions for bulk upload</h1>
    <p class="meta">The start of the filename decides where each picture goes &middot; {{ $generatedAt->format('j M Y') }}</p>

    <ul class="general">
        @foreach ($general as $line)
            <li>{{ $line }}</li>
        @endforeach
    </ul>

    <table>
        @foreach ($groups as $group)
            <tr><th colspan="4">{{ $group['title'] }}</th></tr>

            @foreach ($group['rules'] as $rule)
                <tr>
                    <td class="prefix">{{ $rule['prefix'] }}</td>
                    <td class="makes">{{ $rule['makes'] }}</td>
                    <td class="pattern">{{ $rule['pattern'] }}</td>
                    <td class="example">{{ $rule['example'] }}</td>
                </tr>
            @endforeach
        @endforeach
    </table>
</body>
</html>
