@php
    /** @var \App\Models\ImageHealthRun|null $run */
    $broken = $run?->broken_count ?? 0;
@endphp

<style>
    .ih { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; }
    @media (max-width: 1000px) { .ih { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
    .ih-card { padding: 16px 18px; border: 1px solid #e2e8f0; border-radius: 14px; background: #fff; }
    .ih-k { font-size: 10.5px; font-weight: 600; letter-spacing: 0.06em; text-transform: uppercase; color: #94a3b8; }
    .ih-v { margin-top: 4px; font-size: 22px; font-weight: 650; letter-spacing: -0.02em; font-variant-numeric: tabular-nums; color: #0f172a; }
    .ih-s { margin-top: 2px; font-size: 12.5px; color: #64748b; }
    .ih-good .ih-v { color: #047857; }
    .ih-bad .ih-v { color: #b91c1c; }
    .ih-where { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 12px; }
    .ih-chip { padding: 4px 10px; border-radius: 999px; font-size: 12px; font-weight: 500; color: #475569; background: #f1f5f9; }
    .ih-chip b { color: #0f172a; font-weight: 650; }

    .dark .ih-card { background: #18181b; border-color: rgba(255, 255, 255, 0.08); }
    .dark .ih-k { color: #71717a; }
    .dark .ih-v { color: #f4f4f5; }
    .dark .ih-s { color: #a1a1aa; }
    .dark .ih-good .ih-v { color: #34d399; }
    .dark .ih-bad .ih-v { color: #f87171; }
    .dark .ih-chip { color: #a1a1aa; background: rgba(255, 255, 255, 0.06); }
    .dark .ih-chip b { color: #f4f4f5; }
</style>

<div>
    <div class="ih">
        <div class="ih-card">
            <div class="ih-k">Pictures tested</div>
            <div class="ih-v">{{ $run ? number_format($run->checked_count) : '—' }}</div>
            <div class="ih-s">{{ $run?->cloudflare_checked ? 'Cloudflare ones against your account' : ($run ? 'Each one fetched' : 'Not checked yet') }}</div>
        </div>
        <div class="ih-card {{ $run ? ($broken ? 'ih-bad' : 'ih-good') : '' }}">
            <div class="ih-k">Not loading</div>
            <div class="ih-v">{{ $run ? number_format($broken) : '—' }}</div>
            <div class="ih-s">{{ ! $run ? 'Press “Check now”' : ($broken ? 'Listed below, with where to fix each' : 'Everything loads') }}</div>
        </div>
        <div class="ih-card">
            <div class="ih-k">Last checked</div>
            <div class="ih-v" style="font-size: 18px;" title="{{ $run?->finished_at?->format('j M Y, H:i') }}">{{ $run?->finished_at?->diffForHumans() ?? 'Never' }}</div>
            <div class="ih-s">{{ $run ? 'Took '.$run->seconds().'s · runs nightly at 04:00' : 'Runs nightly at 04:00' }}</div>
        </div>
        <div class="ih-card">
            <div class="ih-k">How to fix</div>
            <div class="ih-s" style="margin-top: 6px; line-height: 1.5;">“Upload again” opens the right page. For many at once, use Bulk upload with the same file names.</div>
        </div>
    </div>

    @if ($bySource->isNotEmpty())
        <div class="ih-where">
            @foreach ($bySource as $source => $total)
                <span class="ih-chip">{{ $source }} <b>{{ $total }}</b></span>
            @endforeach
        </div>
    @endif
</div>
