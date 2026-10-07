{{--
    Shared look for the dashboard's own widgets. Filament ships its admin CSS
    prebuilt, so these carry their styles with them rather than relying on
    Tailwind classes that would not exist.
--}}
@once
<style>
    .dw { font-family: inherit; color: #0f172a; }
    .dw * { box-sizing: border-box; }
    .dw-muted { color: #64748b; }
    .dw-faint { color: #94a3b8; }
    .dw-num { font-variant-numeric: tabular-nums; }
    .dw-kicker { font-size: 11px; font-weight: 600; letter-spacing: 0.06em; text-transform: uppercase; color: #94a3b8; }
    .dw-total { font-size: 12px; font-weight: 600; color: #475569; background: #f1f5f9; border-radius: 999px; padding: 3px 10px; }

    .dw-track { height: 6px; overflow: hidden; background: #f1f5f9; border-radius: 999px; }
    .dw-fill { height: 100%; border-radius: 999px; transition: width 0.6s cubic-bezier(0.16, 1, 0.3, 1); }

    .dw-empty { display: flex; flex-direction: column; align-items: center; gap: 6px; padding: 28px 12px; text-align: center; }
    .dw-empty p { margin: 0; font-size: 13px; color: #94a3b8; }
    .dw-empty strong { font-size: 13px; font-weight: 600; color: #475569; }

    .dw-foot { display: flex; flex-wrap: wrap; gap: 8px 22px; margin-top: 16px; padding-top: 14px; border-top: 1px solid #f1f5f9; }
    .dw-foot div { display: flex; flex-direction: column; gap: 2px; }
    .dw-foot b { font-size: 16px; font-weight: 600; letter-spacing: -0.01em; }

    @media (prefers-reduced-motion: reduce) { .dw-fill { transition: none; } }
</style>
@endonce
