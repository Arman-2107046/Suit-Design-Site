{{--
    Swatch cards for the catalogue screens (App\Filament\Support\SwatchCards):
    a large picture, the name and price, and the on/off switch underneath.
    Light and dark both, following the panel.
--}}
<style>
    .fi-ta-record.ct-swatch {
        position: relative;
        padding: 10px;
        border-radius: 18px;
        transition: transform 0.22s cubic-bezier(0.16, 1, 0.3, 1), box-shadow 0.22s ease;
    }
    .fi-ta-record.ct-swatch:hover {
        transform: translateY(-3px);
        box-shadow: 0 18px 36px -22px rgba(15, 23, 42, 0.45);
    }
    .ct-swatch .fi-ta-record-content-ctn { align-items: stretch; }
    .ct-swatch .fi-ta-record-content { width: 100%; }
    .ct-swatch .fi-ta-record-checkbox { position: absolute; top: 18px; left: 18px; z-index: 2; box-shadow: 0 1px 3px rgba(15, 23, 42, 0.3); }

    /* The picture */
    .ct-swatch .ct-swatch-media,
    .ct-swatch .ct-swatch-media > * { display: block; width: 100%; }
    .ct-swatch-img {
        display: block;
        width: 100% !important;
        height: auto !important;
        max-width: none !important;
        aspect-ratio: 4 / 3;
        border-radius: 12px;
        background: #f1f5f9;
        transition: transform 0.6s cubic-bezier(0.16, 1, 0.3, 1), filter 0.3s ease, opacity 0.3s ease;
    }
    .ct-swatch .ct-swatch-media { overflow: hidden; border-radius: 12px; }
    .ct-swatch:hover .ct-fit-cover { transform: scale(1.04); }
    .ct-fit-cover { object-fit: cover; }
    /* A button is an object, not a cloth: shown whole, on a soft plate */
    .ct-fit-contain { object-fit: contain; padding: 22px; background: radial-gradient(circle at 50% 38%, #ffffff 0%, #f1f5f9 70%); }

    /* A picture that would not load (deleted, or on a service that is gone) */
    .ct-swatch .ct-swatch-media { position: relative; }
    .ct-img-missing { visibility: hidden; }
    .ct-swatch-media:has(.ct-img-missing) {
        background: repeating-linear-gradient(45deg, #f8fafc 0 10px, #f1f5f9 10px 20px);
        box-shadow: inset 0 0 0 1px #e2e8f0;
    }
    .ct-swatch-media:has(.ct-img-missing)::after {
        content: "Picture missing · upload it again";
        position: absolute; inset: 0; display: flex; align-items: center; justify-content: center;
        padding: 12px; text-align: center; font-size: 12px; font-weight: 500; color: #94a3b8;
    }
    .dark .ct-swatch-media:has(.ct-img-missing) {
        background: repeating-linear-gradient(45deg, #1f1f23 0 10px, #18181b 10px 20px);
        box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.08);
    }
    .dark .ct-swatch-media:has(.ct-img-missing)::after { color: #71717a; }

    /* Words */
    .ct-swatch-name { font-size: 14px; letter-spacing: -0.005em; }
    .ct-swatch-price { font-variant-numeric: tabular-nums; font-weight: 600; }

    /* The switch row */
    .ct-swatch-foot {
        align-items: center;
        margin-top: 2px;
        padding-top: 10px;
        border-top: 1px solid rgba(148, 163, 184, 0.2);
    }

    /* Hidden from customers: greyed out until switched back on */
    .ct-swatch-off .ct-swatch-img { filter: grayscale(1); opacity: 0.5; }
    .ct-swatch-off .ct-swatch-name { opacity: 0.6; }

    /* Dark */
    .dark .fi-ta-record.ct-swatch:hover { box-shadow: 0 18px 40px -22px rgba(0, 0, 0, 0.85); }
    .dark .ct-swatch-img { background: #1e293b; }
    .dark .ct-fit-contain { background: radial-gradient(circle at 50% 38%, #f8fafc 0%, #cbd5e1 75%); }
    .dark .ct-swatch-foot { border-top-color: rgba(255, 255, 255, 0.08); }

    @media (prefers-reduced-motion: reduce) {
        .fi-ta-record.ct-swatch, .ct-swatch-img { transition: none; }
        .fi-ta-record.ct-swatch:hover { transform: none; }
        .ct-swatch:hover .ct-fit-cover { transform: none; }
    }
</style>
