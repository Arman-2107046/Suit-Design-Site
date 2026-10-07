@php
    $checks = [
        ['STRIPE_KEY', 'Publishable key (pk_…)', $hasKey],
        ['STRIPE_SECRET', 'Secret key (sk_…)', $hasSecret],
        ['STRIPE_WEBHOOK_SECRET', 'Webhook signing secret (whsec_…)', $hasWebhookSecret],
    ];
    $steps = [
        ['Get your API keys', 'In Stripe, open Developers → API keys. Start with the test keys (pk_test_ and sk_test_) to try everything safely.'],
        ['Add the webhook endpoint', 'Developers → Webhooks → Add endpoint. Paste the URL below and select the five events listed.'],
        ['Copy the signing secret', 'Open the new endpoint and reveal its signing secret (whsec_…).'],
        ['Put all three in .env', 'On the server, set STRIPE_KEY, STRIPE_SECRET and STRIPE_WEBHOOK_SECRET in .env, then run php8.4 artisan optimize:clear.'],
        ['Try a test payment', 'Place an order with card 4242 4242 4242 4242, any future date and any CVC. It should arrive here as Paid.'],
        ['Go live', 'Swap in the live keys and a live-mode webhook endpoint with its own secret.'],
    ];
@endphp

<style>
    .sp { display: grid; gap: 16px; color: #0f172a; }
    .sp-card { border: 1px solid #e2e8f0; border-radius: 16px; background: #fff; box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04); overflow: hidden; }
    .sp-hero { display: flex; flex-wrap: wrap; align-items: center; gap: 16px 24px; padding: 20px 22px; background: linear-gradient(180deg, #f8fafc, #fff); }
    .sp-mark { display: flex; align-items: center; justify-content: center; width: 44px; height: 44px; border-radius: 12px; background: #eff6ff; color: #2563eb; box-shadow: inset 0 0 0 1px #dbeafe; }
    .sp-mark svg { width: 22px; height: 22px; }
    .sp-title { font-size: 15px; font-weight: 650; }
    .sp-sub { margin-top: 2px; font-size: 13px; color: #64748b; }
    .sp-pills { display: flex; flex-wrap: wrap; gap: 8px; }
    .sp-pill { display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px; border-radius: 999px; font-size: 12px; font-weight: 600; }
    .sp-pill i { width: 7px; height: 7px; border-radius: 50%; }
    .sp-on { background: #ecfdf5; color: #047857; } .sp-on i { background: #10b981; box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.18); }
    .sp-off { background: #f1f5f9; color: #475569; } .sp-off i { background: #94a3b8; }
    .sp-test { background: #fffbeb; color: #92400e; } .sp-live { background: #eff6ff; color: #1d4ed8; }
    .sp-stats { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); border-top: 1px solid #f1f5f9; }
    .sp-stat { padding: 12px 22px; border-left: 1px solid #f1f5f9; }
    .sp-stat:first-child { border-left: 0; }
    .sp-stat dt { font-size: 10.5px; font-weight: 600; letter-spacing: 0.06em; text-transform: uppercase; color: #94a3b8; }
    .sp-stat dd { margin: 3px 0 0; font-size: 15px; font-weight: 650; font-variant-numeric: tabular-nums; }

    .sp-body { padding: 18px 22px 20px; }
    .sp-h { font-size: 13px; font-weight: 650; margin: 0 0 10px; }
    .sp-grid { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); gap: 20px; }
    @media (max-width: 1000px) { .sp-grid { grid-template-columns: 1fr; } }
    .sp-check { display: flex; align-items: center; gap: 10px; padding: 9px 0; border-bottom: 1px solid #f8fafc; font-size: 13px; }
    .sp-check:last-child { border-bottom: 0; }
    .sp-check code { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 12px; color: #1e293b; }
    .sp-check span { color: #64748b; font-size: 12.5px; }
    .sp-dot { display: inline-flex; align-items: center; justify-content: center; width: 20px; height: 20px; border-radius: 999px; flex-shrink: 0; }
    .sp-dot svg { width: 13px; height: 13px; }
    .sp-yes { background: #ecfdf5; color: #059669; } .sp-no { background: #f1f5f9; color: #94a3b8; }

    .sp-url { display: flex; align-items: stretch; border: 1px solid #e2e8f0; border-radius: 12px; background: #f8fafc; overflow: hidden; }
    .sp-url code { flex: 1; min-width: 0; padding: 11px 14px; font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 12.5px; white-space: nowrap; overflow-x: auto; }
    .sp-copy { display: inline-flex; align-items: center; gap: 6px; padding: 0 16px; border: 0; border-left: 1px solid #e2e8f0; background: #fff; font-size: 13px; font-weight: 600; color: #2563eb; cursor: pointer; }
    .sp-copy:hover { background: #eff6ff; }
    .sp-copy svg { width: 15px; height: 15px; }
    .sp-events { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 10px; }
    .sp-events code { padding: 4px 9px; border-radius: 8px; background: #f1f5f9; font-size: 12px; font-family: ui-monospace, SFMono-Regular, Menlo, monospace; color: #334155; }
    .sp-warn { margin-top: 10px; padding: 10px 12px; border-radius: 10px; background: #fffbeb; color: #92400e; font-size: 12.5px; box-shadow: inset 0 0 0 1px #fde68a; }

    .sp-steps { counter-reset: step; display: grid; gap: 10px; margin: 0; padding: 0; list-style: none; }
    .sp-steps li { display: grid; grid-template-columns: 22px minmax(0, 1fr); gap: 10px; }
    .sp-steps li::before { counter-increment: step; content: counter(step); display: flex; align-items: center; justify-content: center; width: 22px; height: 22px; border-radius: 999px; background: #2563eb; color: #fff; font-size: 11px; font-weight: 600; }
    .sp-steps b { display: block; font-size: 13px; }
    .sp-steps p { margin: 2px 0 0; font-size: 12.5px; line-height: 1.5; color: #64748b; }

    .sp-row { display: grid; grid-template-columns: minmax(0, 1fr) auto auto; align-items: center; gap: 14px; padding: 11px 22px; border-top: 1px solid #f8fafc; font-size: 13px; }
    .sp-muted { color: #64748b; }
    .sp-empty { grid-template-columns: 1fr; color: #94a3b8; }
    .sp-row a { color: #2563eb; font-weight: 600; text-decoration: none; font-size: 12.5px; }
    .sp-badge { padding: 3px 9px; border-radius: 999px; font-size: 11.5px; font-weight: 600; }
    .sp-paid { background: #ecfdf5; color: #047857; } .sp-pending { background: #f1f5f9; color: #475569; }
    .sp-failed { background: #fef2f2; color: #b91c1c; } .sp-refunded { background: #fff7ed; color: #c2410c; }
</style>

<div class="sp">
    <div class="sp-card">
        <div class="sp-hero">
            <span class="sp-mark"><x-filament::icon icon="heroicon-o-credit-card" /></span>
            <div style="flex: 1; min-width: 220px;">
                <div class="sp-title">Card payments</div>
                <div class="sp-sub">
                    @if ($enabled)
                        Checkout offers “Card” first. Customers pay on Stripe, then return to their order.
                    @else
                        Off. Checkout offers pay on delivery and bank transfer until the keys below are added.
                    @endif
                </div>
            </div>
            <div class="sp-pills">
                <span class="sp-pill {{ $enabled ? 'sp-on' : 'sp-off' }}"><i></i>{{ $enabled ? 'Accepting cards' : 'Not set up' }}</span>
                @if ($mode)
                    <span class="sp-pill {{ $mode === 'live' ? 'sp-live' : 'sp-test' }}">{{ $mode === 'live' ? 'Live: real money' : 'Test mode' }}</span>
                @endif
            </div>
        </div>
        <dl class="sp-stats" style="margin: 0;">
            <div class="sp-stat"><dt>Paid by card</dt><dd>{{ number_format($paidCount) }}</dd></div>
            <div class="sp-stat"><dt>Card revenue</dt><dd>${{ number_format($paidTotal, 2) }}</dd></div>
            <div class="sp-stat"><dt>Awaiting payment</dt><dd>{{ number_format($awaiting) }}</dd></div>
        </dl>
    </div>

    <div class="sp-grid">
        <div class="sp-card">
            <div class="sp-body">
                <p class="sp-h">Keys in .env</p>
                @foreach ($checks as [$name, $label, $ok])
                    <div class="sp-check">
                        <span class="sp-dot {{ $ok ? 'sp-yes' : 'sp-no' }}"><x-filament::icon :icon="$ok ? 'heroicon-m-check' : 'heroicon-m-minus'" /></span>
                        <div><code>{{ $name }}</code><br><span>{{ $label }}{{ $ok ? '' : ' · not set' }}</span></div>
                    </div>
                @endforeach
                @if ($enabled && ! $hasWebhookSecret)
                    <div class="sp-warn">Payments work, but without the webhook secret an order is only marked paid when the customer returns to the site. Add it so every payment is confirmed.</div>
                @endif
            </div>
        </div>

        <div class="sp-card">
            <div class="sp-body" x-data="{ copied: false }">
                <p class="sp-h">Webhook endpoint</p>
                <div class="sp-url">
                    <code>{{ $webhookUrl }}</code>
                    <button type="button" class="sp-copy" x-on:click="navigator.clipboard.writeText(@js($webhookUrl)).then(() => { copied = true; setTimeout(() => copied = false, 1800) })">
                        <span x-show="! copied" style="display: inline-flex;"><x-filament::icon icon="heroicon-m-clipboard-document" /></span>
                        <span x-show="copied" x-cloak style="display: inline-flex;"><x-filament::icon icon="heroicon-m-check" /></span>
                        <span x-text="copied ? 'Copied' : 'Copy'">Copy</span>
                    </button>
                </div>
                <p class="sp-h" style="margin-top: 14px;">Events to select</p>
                <div class="sp-events">
                    @foreach ($events as $event)
                        <code>{{ $event }}</code>
                    @endforeach
                </div>
                @unless ($isPublic)
                    <div class="sp-warn">This is your local address, which Stripe cannot reach. Use the live site’s admin for the real endpoint, or the Stripe CLI to forward events while testing locally.</div>
                @endunless
            </div>
        </div>
    </div>

    <div class="sp-card">
        <div class="sp-body">
            <p class="sp-h">Setting it up</p>
            <ol class="sp-steps">
                @foreach ($steps as [$title, $text])
                    <li><div><b>{{ $title }}</b><p>{{ $text }}</p></div></li>
                @endforeach
            </ol>
        </div>
    </div>

    <div class="sp-card">
        <div class="sp-body" style="padding-bottom: 8px;"><p class="sp-h" style="margin: 0;">Recent card orders</p></div>
        @forelse ($recent as $order)
            <div class="sp-row">
                <div>
                    <a href="{{ \App\Filament\Resources\Orders\OrderResource::getUrl('view', ['record' => $order]) }}">{{ $order->number }}</a>
                    <span class="sp-muted"> · {{ $order->email }} · {{ $order->created_at->diffForHumans() }}</span>
                </div>
                <span style="font-weight: 600; font-variant-numeric: tabular-nums;">${{ number_format((float) $order->total, 2) }}</span>
                <span style="display: flex; gap: 10px; align-items: center;">
                    <span class="sp-badge sp-{{ $order->payment_status }}">{{ \App\Services\Payments\OrderPayments::STATUSES[$order->payment_status] ?? $order->payment_status }}</span>
                    @if ($order->stripe_payment_intent_id)
                        <a href="{{ $stripe->dashboardUrl($order->stripe_payment_intent_id) }}" target="_blank" rel="noopener">Stripe ↗</a>
                    @endif
                </span>
            </div>
        @empty
            <div class="sp-row sp-empty">No card orders yet.</div>
        @endforelse
    </div>
</div>
