@php
    /** @var \App\Models\RankYakSetting $settings */
    $live = $settings->enabled;
    $steps = [
        ['Open RankYak', 'Go to your project’s Settings → Integrations, then the Developer zone.'],
        ['Enable Webhook', 'Click Enable next to Webhook and paste the URL above.'],
        ['Optional: add the API key', 'Copy the key from RankYak’s API integration into the Connection section below. It unlocks Sync now and URL reporting.'],
        ['Switch it on here', 'Turn on “Accept articles from RankYak” and save. New articles arrive in the journal by themselves.'],
    ];
@endphp

<style>
    .ry { display: grid; gap: 16px; color: #0f172a; }
    .ry-card { border: 1px solid #e2e8f0; border-radius: 16px; background: #fff; box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04); overflow: hidden; }
    .ry-hero { display: flex; flex-wrap: wrap; align-items: center; gap: 16px 24px; padding: 20px 22px; background: linear-gradient(180deg, #f8fafc, #fff); }
    .ry-mark { display: flex; align-items: center; justify-content: center; width: 44px; height: 44px; border-radius: 12px; background: #eff6ff; color: #2563eb; box-shadow: inset 0 0 0 1px #dbeafe; }
    .ry-mark svg { width: 22px; height: 22px; }
    .ry-title { font-size: 15px; font-weight: 650; }
    .ry-sub { margin-top: 2px; font-size: 13px; color: #64748b; }
    .ry-pill { display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px; border-radius: 999px; font-size: 12px; font-weight: 600; }
    .ry-pill i { width: 7px; height: 7px; border-radius: 50%; }
    .ry-on { background: #ecfdf5; color: #047857; } .ry-on i { background: #10b981; box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.18); }
    .ry-off { background: #f1f5f9; color: #475569; } .ry-off i { background: #94a3b8; }
    .ry-stats { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); border-top: 1px solid #f1f5f9; }
    .ry-stat { padding: 12px 22px; border-left: 1px solid #f1f5f9; min-width: 0; }
    .ry-stat:first-child { border-left: 0; }
    .ry-stat dt { font-size: 10.5px; font-weight: 600; letter-spacing: 0.06em; text-transform: uppercase; color: #94a3b8; }
    .ry-stat dd { margin: 3px 0 0; font-size: 14px; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    @media (max-width: 900px) { .ry-stats { grid-template-columns: repeat(2, minmax(0, 1fr)); } .ry-stat:nth-child(3) { border-left: 0; } }

    .ry-body { padding: 18px 22px 20px; }
    .ry-label { font-size: 13px; font-weight: 600; margin-bottom: 8px; display: flex; align-items: center; justify-content: space-between; gap: 8px; }
    .ry-url { display: flex; align-items: stretch; border: 1px solid #e2e8f0; border-radius: 12px; background: #f8fafc; overflow: hidden; }
    .ry-url code { flex: 1; min-width: 0; padding: 11px 14px; font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 12.5px; color: #1e293b; white-space: nowrap; overflow-x: auto; }
    .ry-copy { display: inline-flex; align-items: center; gap: 6px; padding: 0 16px; border: 0; border-left: 1px solid #e2e8f0; background: #fff; font-size: 13px; font-weight: 600; color: #2563eb; cursor: pointer; transition: background 0.15s ease; }
    .ry-copy:hover { background: #eff6ff; }
    .ry-copy svg { width: 15px; height: 15px; }
    .ry-hint { font-size: 12px; font-weight: 500; color: #94a3b8; }
    .ry-note { margin-top: 8px; font-size: 12.5px; color: #64748b; }
    .ry-warn { margin-top: 10px; padding: 10px 12px; border-radius: 10px; background: #fffbeb; color: #92400e; font-size: 12.5px; box-shadow: inset 0 0 0 1px #fde68a; }
    .ry-error { margin-top: 10px; padding: 10px 12px; border-radius: 10px; background: #fef2f2; color: #991b1b; font-size: 12.5px; box-shadow: inset 0 0 0 1px #fecaca; }

    .ry-steps { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; margin-top: 18px; }
    @media (max-width: 1100px) { .ry-steps { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
    @media (max-width: 640px) { .ry-steps { grid-template-columns: 1fr; } }
    .ry-step { padding: 12px 14px; border-radius: 12px; background: #f8fafc; box-shadow: inset 0 0 0 1px #eef2f6; }
    .ry-step b { display: flex; align-items: center; gap: 8px; font-size: 13px; }
    .ry-step b span { display: inline-flex; align-items: center; justify-content: center; width: 20px; height: 20px; border-radius: 999px; background: #2563eb; color: #fff; font-size: 11px; }
    .ry-step p { margin: 6px 0 0; font-size: 12.5px; line-height: 1.5; color: #64748b; }
</style>

<div class="ry">
    <div class="ry-card">
        <div class="ry-hero">
            <span class="ry-mark"><x-filament::icon icon="heroicon-o-bolt" /></span>
            <div style="flex: 1; min-width: 220px;">
                <div class="ry-title">RankYak → Journal</div>
                <div class="ry-sub">Each article RankYak publishes becomes a journal post, with its SEO title, meta description and header image.</div>
            </div>
            <span class="ry-pill {{ $live ? 'ry-on' : 'ry-off' }}"><i></i>{{ $live ? 'Receiving articles' : 'Switched off' }}</span>
        </div>

        <dl class="ry-stats" style="margin: 0;">
            <div class="ry-stat"><dt>Imported</dt><dd>{{ number_format($imported) }} {{ str('post')->plural($imported) }}</dd></div>
            <div class="ry-stat"><dt>Last article in</dt><dd title="{{ $settings->last_webhook_at?->format('j M Y, H:i') }}">{{ $settings->last_webhook_at?->diffForHumans() ?? 'Not yet' }}</dd></div>
            <div class="ry-stat"><dt>Last sync</dt><dd title="{{ $settings->last_synced_at?->format('j M Y, H:i') }}">{{ $settings->last_synced_at?->diffForHumans() ?? 'Never' }}</dd></div>
            <div class="ry-stat"><dt>API key</dt><dd>{{ $settings->hasApiKey() ? 'Saved' : 'Not added' }}</dd></div>
        </dl>
    </div>

    <div class="ry-card">
        <div class="ry-body" x-data="{ copied: false }">
            <div class="ry-label">
                <span>Webhook URL</span>
                <span class="ry-hint">Paste into RankYak → Settings → Integrations → Webhook</span>
            </div>

            <div class="ry-url">
                <code>{{ $webhookUrl }}</code>
                <button type="button" class="ry-copy"
                    x-on:click="navigator.clipboard.writeText(@js($webhookUrl)).then(() => { copied = true; setTimeout(() => copied = false, 1800) })">
                    <span x-show="! copied" style="display: inline-flex;"><x-filament::icon icon="heroicon-m-clipboard-document" /></span>
                    <span x-show="copied" x-cloak style="display: inline-flex;"><x-filament::icon icon="heroicon-m-check" /></span>
                    <span x-text="copied ? 'Copied' : 'Copy'">Copy</span>
                </button>
            </div>
            <p class="ry-note">The long code at the end is the key: anyone with this URL can post articles, so keep it private. “New webhook URL” above replaces it.</p>

            @unless ($isPublic)
                <div class="ry-warn">This address is on your computer, so RankYak cannot reach it. Use the URL from the live site’s admin once it is deployed.</div>
            @endunless

            @if ($settings->last_error)
                <div class="ry-error"><strong>Last problem</strong> ({{ $settings->last_error_at?->diffForHumans() }}): {{ $settings->last_error }}</div>
            @endif

            <div class="ry-steps">
                @foreach ($steps as $i => [$title, $text])
                    <div class="ry-step"><b><span>{{ $i + 1 }}</span>{{ $title }}</b><p>{{ $text }}</p></div>
                @endforeach
            </div>
        </div>
    </div>
</div>
