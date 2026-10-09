{{--
    Shared admin styles, and the dark versions of every screen built here
    (Filament's own components switch themselves). Each custom view keeps its
    light styles beside its markup; this file only says what changes in the
    dark. The greys are Filament's zinc, so custom cards sit flush with its own.

      surface #18181b · raised #1f1f23 · inset #27272a · line rgba(255,255,255,.08)
      text #f4f4f5 · body #d4d4d8 · muted #a1a1aa · faint #71717a
--}}
<style>
    /* ── Shared: people in tables (Administrators, Activity log) ── */
    .ct-person-name { display: flex; align-items: center; gap: 6px; font-size: 14px; font-weight: 600; color: #0f172a; }
    .ct-person-name > span:first-child { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .ct-person-unknown { color: #94a3b8; }
    .ct-person-sub { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: 12.5px; color: #64748b; }
    .ct-person-you { padding: 1px 7px; border-radius: 9999px; background: #eef2ff; color: #4338ca; font-size: 10.5px; font-weight: 600; }
    .ct-avatar { box-shadow: 0 0 0 2px #fff, 0 1px 3px rgb(15 23 42 / 0.18); }

    .dark .ct-person-name { color: #f4f4f5; }
    .dark .ct-person-unknown { color: #71717a; }
    .dark .ct-person-sub { color: #a1a1aa; }
    .dark .ct-person-you { background: rgba(99, 102, 241, 0.18); color: #c7d2fe; }
    .dark .ct-avatar { box-shadow: 0 0 0 2px #18181b, 0 1px 3px rgb(0 0 0 / 0.5); }

    /* ── Sidebar ─────────────────────────────────────────────── */
    .dark .fi-sidebar-nav {
        --nav-ink: var(--primary-500);
        --nav-ink-text: var(--primary-300);
        --nav-text: #d4d4d8;
        --nav-muted: #a1a1aa;
        --nav-line: rgba(255, 255, 255, 0.08);
        --nav-chip: rgba(255, 255, 255, 0.06);
        --nav-chip-hover: rgba(255, 255, 255, 0.1);
    }
    .dark .fi-sidebar-group-btn { background: #18181b; box-shadow: inset 0 0 0 1px var(--nav-line); }
    .dark .fi-sidebar-group-btn:hover { box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.16), 0 4px 12px -6px rgba(0, 0, 0, 0.6); }
    .dark .fi-sidebar-group-label::before { background: var(--grp-icon-dark) center / 16px no-repeat, var(--nav-chip); box-shadow: inset 0 0 0 1px var(--nav-line); }
    .dark .fi-sidebar-group-btn:hover .fi-sidebar-group-label::before { background-color: var(--nav-chip-hover); }
    .dark .fi-sidebar-group:not(.fi-collapsed) > .fi-sidebar-group-btn { background: linear-gradient(180deg, #1f1f23, #18181b); box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.14), 0 6px 16px -10px rgba(0, 0, 0, 0.7); }
    .dark .fi-sidebar-group.fi-active .fi-sidebar-group-label::before { background: var(--grp-icon-active) center / 16px no-repeat, var(--nav-ink); box-shadow: 0 4px 10px -4px color-mix(in oklab, var(--primary-500) 60%, transparent); }
    .dark .fi-sidebar-group:has(> .fi-sidebar-group-btn) .fi-sidebar-item-btn { background: transparent; box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.05); }
    .dark .fi-sidebar-group:has(> .fi-sidebar-group-btn) .fi-sidebar-item-btn:hover { background: #18181b; box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.14), 0 4px 12px -6px rgba(0, 0, 0, 0.6); }
    .dark .fi-sidebar-group:has(> .fi-sidebar-group-btn) .fi-sidebar-item.fi-active > .fi-sidebar-item-btn { background: color-mix(in oklab, var(--primary-500) 12%, transparent); box-shadow: inset 0 0 0 1px color-mix(in oklab, var(--primary-500) 40%, transparent); }

    /* Bulk upload's highlighted menu entry */
    .dark .fi-sidebar-item > a[href$="/admin/bulk-upload"] { color: var(--primary-200); background: linear-gradient(90deg, color-mix(in oklab, var(--primary-500) 28%, transparent), color-mix(in oklab, var(--primary-500) 8%, transparent)); box-shadow: inset 0 0 0 1px color-mix(in oklab, var(--primary-400) 35%, transparent); }
    .dark .fi-sidebar-item > a[href$="/admin/bulk-upload"] .fi-sidebar-item-icon { color: var(--primary-300); }

    /* ── Dashboard ───────────────────────────────────────────── */
    /* Sales overview: the year's total and its change on last year (SalesOverview) */
    .so-total { font-size: 30px; font-weight: 600; letter-spacing: -0.03em; line-height: 1.1; color: #0f172a; font-variant-numeric: tabular-nums; }
    .so-change { display: inline-flex; align-items: center; gap: 4px; padding: 3px 9px; border-radius: 999px; font-size: 12px; font-weight: 600; }
    .so-up { color: #047857; background: #d1fae5; }
    .so-down { color: #b91c1c; background: #fee2e2; }
    .dark .so-total { color: #f4f4f5; }
    .dark .so-up { color: #6ee7b7; background: rgba(16, 185, 129, 0.16); }
    .dark .so-down { color: #fca5a5; background: rgba(239, 68, 68, 0.16); }

    .dark .dw { color: #f4f4f5; }
    .dark .dw-muted { color: #a1a1aa; }
    .dark .dw-faint, .dark .dw-kicker { color: #71717a; }
    .dark .dw-total { color: #d4d4d8; background: rgba(255, 255, 255, 0.06); }
    .dark .dw-track { background: rgba(255, 255, 255, 0.07); }
    .dark .dw-empty p { color: #71717a; }
    .dark .dw-empty strong { color: #d4d4d8; }
    .dark .dw-foot { border-top-color: rgba(255, 255, 255, 0.06); }

    .dark .op-row:hover, .dark a.tf-row:hover { background: rgba(255, 255, 255, 0.04); }
    .dark .op-label { color: #e4e4e7; }
    .dark .op-share { color: #71717a; }

    .dark .tf-rank { color: #52525b; }
    .dark .tf-swatch { background: #27272a; box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.06); }
    .dark .tf-name { color: #f4f4f5; }
    .dark .tf-meta, .dark .tf-count span { color: #71717a; }

    .dark .dt-card { background: #1f1f23; border-color: rgba(255, 255, 255, 0.06); }
    .dark .dt-donut::after { background: #1f1f23; }
    .dark .dt-centre span { color: #71717a; }
    .dark .dt-empty-ring { background: #27272a; }
    .dark .dt-name { color: #d4d4d8; }
    .dark .dt-legend li:first-child .dt-name { color: #f4f4f5; }
    .dark .dt-pct { color: #a1a1aa; }

    .dark .cm-map { background: linear-gradient(180deg, #1c1c20, #18181b); }
    .dark .cm-map .jvm-tooltip { color: #18181b; background: #f4f4f5; box-shadow: 0 12px 28px -12px rgba(0, 0, 0, 0.9); }
    .dark .cm-row { border-bottom-color: rgba(255, 255, 255, 0.06); }
    .dark .cm-sub, .dark .cm-loading { color: #71717a; }

    .dark .ra-item:not(:last-child)::before { background: rgba(255, 255, 255, 0.08); }
    .dark .ra-text { color: #d4d4d8; }
    .dark .ra-text b { color: #f4f4f5; }
    .dark .ra-time { color: #71717a; }
    .dark .ra-tone-success { background: rgba(16, 185, 129, 0.14); color: #34d399; }
    .dark .ra-tone-info { background: color-mix(in oklab, var(--primary-500) 14%, transparent); color: var(--primary-400); }
    .dark .ra-tone-danger { background: rgba(239, 68, 68, 0.14); color: #f87171; }
    .dark .ra-tone-primary { background: rgba(99, 102, 241, 0.16); color: #a5b4fc; }
    .dark .ra-tone-warning { background: rgba(245, 158, 11, 0.14); color: #fbbf24; }
    .dark .ra-tone-gray { background: rgba(255, 255, 255, 0.06); color: #a1a1aa; }

    /* ── Activity log details ────────────────────────────────── */
    .dark .al { color: #d4d4d8; }
    .dark .al-card { background: #18181b; border-color: rgba(255, 255, 255, 0.08); }
    .dark .al-head { background: linear-gradient(180deg, #1f1f23, #18181b); }
    .dark .al-meta { background: rgba(255, 255, 255, 0.06); border-top-color: rgba(255, 255, 255, 0.06); }
    .dark .al-meta div { background: #18181b; }
    .dark .al-meta dt { color: #71717a; }
    .dark .al-meta dd, .dark .al-who, .dark .al-dest, .dark .al-field { color: #f4f4f5; }
    .dark .al-when, .dark .al-title { color: #a1a1aa; }
    .dark .al-faint, .dark .al-none { color: #52525b; }
    .dark .al-num { color: #d4d4d8; }
    .dark .al-table th { background: #1f1f23; color: #71717a; border-bottom-color: rgba(255, 255, 255, 0.08); }
    .dark .al-table td { border-bottom-color: rgba(255, 255, 255, 0.05); }
    .dark .al-old { background: rgba(239, 68, 68, 0.14); color: #fca5a5; text-decoration-color: rgba(252, 165, 165, 0.4); }
    .dark .al-new { background: rgba(16, 185, 129, 0.14); color: #6ee7b7; }
    .dark .al-link { color: var(--primary-300); }
    .dark .al-bar { background: rgba(99, 102, 241, 0.15); }
    .dark .al-warncard { background: rgba(245, 158, 11, 0.1); border-color: rgba(245, 158, 11, 0.3); color: #fde68a; }

    /* ── RankYak and Stripe pages ────────────────────────────── */
    .dark .ry, .dark .sp, .dark .ryi { color: #f4f4f5; }
    .dark .ry-card, .dark .sp-card, .dark .ryi { background: #18181b; border-color: rgba(255, 255, 255, 0.08); }
    .dark .ry-hero, .dark .sp-hero { background: linear-gradient(180deg, #1f1f23, #18181b); }
    .dark .ry-mark, .dark .sp-mark { background: color-mix(in oklab, var(--primary-500) 14%, transparent); color: var(--primary-400); box-shadow: inset 0 0 0 1px color-mix(in oklab, var(--primary-500) 30%, transparent); }
    .dark .ry-sub, .dark .sp-sub, .dark .ry-note, .dark .sp-muted, .dark .ryi-meta { color: #a1a1aa; }
    .dark .ry-hint, .dark .sp-empty, .dark .ryi-empty { color: #71717a; }
    .dark .ryi-empty strong { color: #d4d4d8; }
    .dark .ry-on, .dark .sp-on, .dark .ryi-live, .dark .sp-paid { background: rgba(16, 185, 129, 0.14); color: #6ee7b7; }
    .dark .ry-off, .dark .sp-off, .dark .ryi-draft, .dark .sp-pending { background: rgba(255, 255, 255, 0.06); color: #a1a1aa; }
    .dark .sp-test { background: rgba(245, 158, 11, 0.14); color: #fde68a; }
    .dark .sp-live, .dark .ryi-scheduled { background: color-mix(in oklab, var(--primary-500) 16%, transparent); color: var(--primary-300); }
    .dark .sp-failed { background: rgba(239, 68, 68, 0.14); color: #fca5a5; }
    .dark .sp-refunded { background: rgba(249, 115, 22, 0.14); color: #fdba74; }
    .dark .ryi-reported { background: transparent; color: #a1a1aa; box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.12); }
    .dark .ry-stats, .dark .sp-stats { border-top-color: rgba(255, 255, 255, 0.06); }
    .dark .ry-stat, .dark .sp-stat { border-left-color: rgba(255, 255, 255, 0.06); }
    .dark .ry-stat dt, .dark .sp-stat dt { color: #71717a; }
    .dark .ry-url, .dark .sp-url { background: #1f1f23; border-color: rgba(255, 255, 255, 0.1); }
    .dark .ry-url code, .dark .sp-url code, .dark .sp-check code { color: #e4e4e7; }
    .dark .ry-copy, .dark .sp-copy { background: #18181b; border-left-color: rgba(255, 255, 255, 0.1); color: var(--primary-300); }
    .dark .ry-copy:hover, .dark .sp-copy:hover { background: color-mix(in oklab, var(--primary-500) 12%, transparent); }
    .dark .ry-warn, .dark .sp-warn { background: rgba(245, 158, 11, 0.1); color: #fde68a; box-shadow: inset 0 0 0 1px rgba(245, 158, 11, 0.3); }
    .dark .ry-error { background: rgba(239, 68, 68, 0.1); color: #fca5a5; box-shadow: inset 0 0 0 1px rgba(239, 68, 68, 0.3); }
    .dark .ry-step { background: #1f1f23; box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.06); }
    .dark .ry-step p, .dark .sp-steps p, .dark .sp-check span { color: #a1a1aa; }
    .dark .sp-check { border-bottom-color: rgba(255, 255, 255, 0.05); }
    .dark .sp-yes { background: rgba(16, 185, 129, 0.14); color: #34d399; }
    .dark .sp-no { background: rgba(255, 255, 255, 0.06); color: #71717a; }
    .dark .sp-events code { background: rgba(255, 255, 255, 0.06); color: #d4d4d8; }
    .dark .sp-row, .dark .ryi-row { border-top-color: rgba(255, 255, 255, 0.05); border-bottom-color: rgba(255, 255, 255, 0.05); }
    .dark .sp-row a, .dark .ryi-head a { color: var(--primary-300); }
    .dark .ryi-head { border-bottom-color: rgba(255, 255, 255, 0.06); }
    .dark .ryi-row:hover { background: rgba(255, 255, 255, 0.03); }
    .dark .ryi-thumb { background-color: #27272a; box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.06); }

    /* ── Bulk upload: the uploader ───────────────────────────── */
    .dark .bx { color: #f4f4f5; }
    .dark .bx-drop { background: #1c1c20; border-color: #3f3f46; }
    .dark .bx-drop:hover, .dark .bx-drop.is-over { border-color: #818cf8; background: rgba(99, 102, 241, 0.08); }
    .dark .bx-drop-icon { color: #a5b4fc; background: rgba(99, 102, 241, 0.16); }
    .dark .bx-drop-sub { color: #a1a1aa; }
    .dark .bx-drop-sub b, .dark .bx-chip.mono { color: #a5b4fc; }
    .dark .bx-chip { color: #d4d4d8; background: #27272a; border-color: #3f3f46; }
    .dark .bx-alert { color: #fecaca; background: rgba(239, 68, 68, 0.1); border-color: rgba(239, 68, 68, 0.3); }
    .dark .bx-note { color: #fde68a; background: rgba(245, 158, 11, 0.08); border-color: rgba(245, 158, 11, 0.28); }
    .dark .bx-note-list { border-top-color: rgba(245, 158, 11, 0.28); }
    .dark .bx-note-list li { border-bottom-color: rgba(245, 158, 11, 0.14); }
    .dark .bx-note-list li span:first-child { color: #fcd34d; }
    .dark .bx-note-list li span:last-child { color: #fde68a; }
    .dark .bx-stat, .dark .bx-progress, .dark .bx-panel { background: #18181b; border-color: #27272a; }
    .dark .bx-stat-k, .dark .bx-pct i { color: #71717a; }
    .dark .bx-stat.good .bx-stat-n { color: #34d399; }
    .dark .bx-stat.warn .bx-stat-n { color: #fbbf24; }
    .dark .bx-stat.bad { background: rgba(239, 68, 68, 0.1); border-color: rgba(239, 68, 68, 0.35); }
    .dark .bx-stat.bad .bx-stat-n, .dark .bx-stat.bad .bx-stat-k { color: #f87171; }
    .dark .bx-row.is-failed { background: rgba(239, 68, 68, 0.1); }
    .dark .bx-row.is-failed .bx-name, .dark .bx-row.is-failed .bx-ext { color: #f87171; }
    .dark .bx-row.is-failed .bx-ext { background: rgba(239, 68, 68, 0.16); }
    .dark .bx-fail { background: rgba(239, 68, 68, 0.05); }
    .dark .bx-fail-name, .dark .bx-side .bx-panel-title { color: #f87171; }
    .dark .bx-fail-why { color: #fca5a5; }
    .dark .bx-side { border-color: rgba(239, 68, 68, 0.35); }
    .dark .bx-count.bad { color: #fff; background: #dc2626; }
    .dark .bx-progress-alert { color: #fca5a5; background: rgba(239, 68, 68, 0.1); border-color: rgba(239, 68, 68, 0.35); }
    .dark .bx-bar.has-fail { box-shadow: 0 0 0 2px rgba(239, 68, 68, 0.5); }
    .dark .bx-progress-meta, .dark .bx-progress-foot, .dark .bx-panel-title, .dark .bx-fail-why { color: #a1a1aa; }
    .dark .bx-bar { background: #27272a; }
    .dark .bx-spin { border-color: #3f3f46; border-top-color: #818cf8; }
    .dark .bx-btn-primary { color: #18181b; background: #f4f4f5; }
    .dark .bx-btn-primary:hover:not(:disabled) { background: #e4e4e7; }
    .dark .bx-btn-ghost { color: #e4e4e7; background: #18181b; border-color: #3f3f46; }
    .dark .bx-btn-ghost:hover:not(:disabled) { background: #27272a; border-color: #52525b; }
    .dark .bx-btn-warn { color: #fde68a; background: rgba(245, 158, 11, 0.12); border-color: rgba(245, 158, 11, 0.3); }
    .dark .bx-btn-warn:hover:not(:disabled) { background: rgba(245, 158, 11, 0.2); }
    .dark .bx-btn-stop { color: #fca5a5; background: #18181b; border-color: rgba(239, 68, 68, 0.35); }
    .dark .bx-btn-stop:hover:not(:disabled) { background: rgba(239, 68, 68, 0.12); }
    .dark .bx-panel-head, .dark .bx-row, .dark .bx-fail { border-bottom-color: #27272a; }
    .dark .bx-count { color: #d4d4d8; background: #27272a; }
    .dark .bx-count.warn { color: #fde68a; background: rgba(245, 158, 11, 0.16); }
    .dark .bx-name { color: #e4e4e7; }
    .dark .bx-size, .dark .bx-empty { color: #71717a; }
    .dark .bx-remove:hover { color: #fca5a5; background: rgba(239, 68, 68, 0.12); }
    .dark .bx-more { color: #71717a; background: #1c1c20; }
    .dark .bx-pill.waiting { color: #a1a1aa; background: #27272a; }
    .dark .bx-pill.moving { color: #c7d2fe; background: rgba(99, 102, 241, 0.18); }
    .dark .bx-pill.retrying { color: #fde68a; background: rgba(245, 158, 11, 0.16); }
    .dark .bx-pill.landed { color: #7dd3fc; background: rgba(14, 165, 233, 0.16); }
    .dark .bx-pill.filed { color: #6ee7b7; background: rgba(16, 185, 129, 0.16); }
    .dark .bx-pill.failed { color: #fca5a5; background: rgba(239, 68, 68, 0.16); }
    .dark .bx-pill.refused { color: #fdba74; background: rgba(249, 115, 22, 0.16); }
    .dark .bx-tag { color: #fca5a5; background: rgba(239, 68, 68, 0.16); }
    .dark .bx-tag.filing { color: #fdba74; background: rgba(249, 115, 22, 0.16); }
    .dark .bfu-eyebrow { color: #a5b4fc; }
    .dark .bfu-warn { color: #fbbf24; }

    /* ── Bulk upload: the dock that follows you round the admin ── */
    .dark .bud { background: #18181b; border-color: rgba(255, 255, 255, 0.08); box-shadow: 0 18px 40px -18px rgba(0, 0, 0, 0.8); }
    .dark .bud-title, .dark .bud-pct { color: #f4f4f5; }
    .dark .bud-x { color: #71717a; }
    .dark .bud-x:hover { color: #e4e4e7; }
    .dark .bud-bar { background: #27272a; }
    .dark .bud-meta { color: #a1a1aa; }
    .dark .bud-link { color: #a5b4fc; }
    .dark .bud-bad { color: #fbbf24; }
    .dark .bud-spin { border-color: #3f3f46; border-top-color: #818cf8; }

    /* ── Homepage video upload preview frame ─────────────────── */
    .dark .cv-preview { box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.1); }
</style>
