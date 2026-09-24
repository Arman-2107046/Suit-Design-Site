{{-- Dompdf has no flexbox or grid, so the whole report is laid out with tables. --}}
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Bulk upload — rejected files</title>
    <style>
        @page { margin: 34mm 18mm 22mm; }

        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 10px;
            line-height: 1.5;
            color: #1f2937;
        }

        /* Repeated on every page */
        header {
            position: fixed;
            top: -24mm;
            left: 0;
            right: 0;
            height: 18mm;
        }
        .brand {
            font-size: 9px;
            font-weight: bold;
            letter-spacing: 3px;
            text-transform: uppercase;
            color: #6366f1;
        }
        .rule {
            border-bottom: 1px solid #e5e7eb;
            margin-top: 5px;
        }

        footer {
            position: fixed;
            bottom: -14mm;
            left: 0;
            right: 0;
            font-size: 8px;
            color: #9ca3af;
        }
        footer .page:after {
            content: counter(page);
        }

        h1 {
            margin: 0;
            font-size: 21px;
            font-weight: normal;
            letter-spacing: -0.4px;
            color: #0f172a;
        }
        .meta {
            margin: 4px 0 0;
            font-size: 9px;
            color: #9ca3af;
        }

        table.stats {
            width: 100%;
            /* border-spacing adds 6px outside the first and last cell, so pull it back level with the table below */
            margin: 18px -6px 22px;
            border-collapse: separate;
            border-spacing: 6px 0;
        }
        table.stats td {
            width: 33.33%;
            padding: 11px 13px;
            background: #f8f8fc;
            border: 1px solid #ececf4;
            border-radius: 8px;
        }
        table.stats .n {
            display: block;
            font-size: 19px;
            font-weight: bold;
            color: #0f172a;
        }
        table.stats .k {
            display: block;
            font-size: 8px;
            letter-spacing: 1.2px;
            text-transform: uppercase;
            color: #9ca3af;
        }
        table.stats td.bad .n { color: #b91c1c; }

        table.rows {
            width: 100%;
            border-collapse: collapse;
        }
        table.rows thead th {
            padding: 0 8px 7px;
            font-size: 8px;
            font-weight: bold;
            letter-spacing: 1.2px;
            text-transform: uppercase;
            text-align: left;
            color: #9ca3af;
            border-bottom: 1px solid #e5e7eb;
        }
        table.rows tbody td {
            padding: 9px 8px;
            vertical-align: top;
            border-bottom: 1px solid #f1f1f6;
        }
        table.rows tbody tr:nth-child(even) td { background: #fbfbfe; }

        .idx { width: 22px; color: #c7c7d4; }
        .file { width: 34%; font-weight: bold; color: #111827; word-wrap: break-word; }
        .stage { width: 22%; }
        .reason { color: #4b5563; word-wrap: break-word; }

        .tag {
            display: inline-block;
            padding: 2px 7px;
            font-size: 8px;
            font-weight: bold;
            border-radius: 7px;
            background: #fee2e2;
            color: #b91c1c;
        }
        .tag.filing { background: #ffedd5; color: #c2410c; }

        .note {
            margin-top: 22px;
            padding: 11px 13px;
            background: #f8f8fc;
            border-left: 2px solid #6366f1;
            font-size: 9px;
            color: #4b5563;
        }
        .note b { color: #111827; }
    </style>
</head>
<body>

<header>
    <div class="brand">Custom Tailor</div>
    <div class="rule"></div>
</header>

<footer>
    Generated {{ $generatedAt->format('j F Y, H:i') }}@if ($admin) · {{ $admin }}@endif
    &nbsp;&nbsp;·&nbsp;&nbsp; Page <span class="page"></span>
</footer>

<h1>Bulk upload — rejected files</h1>
<p class="meta">{{ $generatedAt->format('l j F Y \a\t H:i') }}</p>

<table class="stats">
    <tr>
        <td>
            <span class="n">{{ $total }}</span>
            <span class="k">Files in batch</span>
        </td>
        <td>
            <span class="n">{{ $filed }}</span>
            <span class="k">Filed</span>
        </td>
        <td class="bad">
            <span class="n">{{ count($rejected) }}</span>
            <span class="k">Rejected</span>
        </td>
    </tr>
</table>

<table class="rows">
    <thead>
        <tr>
            <th class="idx"></th>
            <th class="file">File</th>
            <th class="stage">Rejected at</th>
            <th class="reason">Reason</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($rejected as $i => $row)
            <tr>
                <td class="idx">{{ $i + 1 }}</td>
                <td class="file">{{ $row['name'] }}</td>
                <td class="stage">
                    <span class="tag {{ str_contains(strtolower($row['stage']), 'filing') ? 'filing' : '' }}">{{ $row['stage'] }}</span>
                </td>
                <td class="reason">{{ $row['reason'] ?: 'No reason given.' }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

<div class="note">
    <b>Filenames decide where an image lands.</b>
    A file rejected at filing reached Cloudinary but its name did not match a prefix, or the record it
    names does not exist yet — for example a fabric image uploaded before its fabric. Fix the name or
    create the parent record, then upload the listed files again.
</div>

</body>
</html>
