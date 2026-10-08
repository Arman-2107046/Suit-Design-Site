{{--
    The admin's logo: a navy "CT" monogram and the name, set in Figtree like the
    shop's header. Drawn inline so it takes the page's font and the theme colour,
    and turns light in dark mode by itself.
--}}
<span class="ct-brand" aria-label="Custom Tailor">
    <svg class="ct-brand-mark" viewBox="0 0 32 32" aria-hidden="true">
        <rect width="32" height="32" rx="9" class="ct-brand-tile" />
        <path d="M5 25.5 C11 23.2 21 23.2 27 25.5" class="ct-brand-thread" />
        <text x="16" y="20.6" text-anchor="middle" class="ct-brand-letters">CT</text>
    </svg>
    <span class="ct-brand-name">Custom Tailor</span>
</span>

<style>
    .ct-brand { display: inline-flex; align-items: center; gap: 10px; height: 100%; color: #0f172a; text-decoration: none; }
    .ct-brand-mark { flex-shrink: 0; width: auto; height: 100%; max-height: 34px; aspect-ratio: 1; filter: drop-shadow(0 2px 4px color-mix(in oklab, var(--primary-900) 30%, transparent)); }
    .ct-brand-tile { fill: var(--primary-600); }
    .ct-brand-thread { fill: none; stroke: #fff; stroke-opacity: 0.35; stroke-width: 1.2; stroke-dasharray: 1.6 1.6; stroke-linecap: round; }
    .ct-brand-letters { fill: #fff; font-family: 'Figtree', ui-sans-serif, system-ui, sans-serif; font-size: 13px; font-weight: 700; letter-spacing: 0.4px; }
    .ct-brand-name { font-family: 'Figtree', ui-sans-serif, system-ui, sans-serif; font-size: 1.15rem; font-weight: 650; letter-spacing: -0.02em; white-space: nowrap; }

    .dark .ct-brand { color: #fafafa; }
    .dark .ct-brand-tile { fill: var(--primary-500); }
</style>
