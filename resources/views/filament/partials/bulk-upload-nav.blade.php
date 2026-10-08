{{--
    The bulk uploader is the one page that writes to nearly every table, so it is
    lifted out of the palette the rest of the sidebar shares and given the brand
    navy as an accent. The glow runs a few times on load, then settles.
--}}
<style>
    .fi-sidebar-item > a[href$="/admin/bulk-upload"] {
        position: relative;
        font-weight: 600;
        color: var(--primary-700);
        background: linear-gradient(90deg, color-mix(in oklab, var(--primary-500) 14%, transparent), color-mix(in oklab, var(--primary-500) 4%, transparent));
        box-shadow: inset 0 0 0 1px color-mix(in oklab, var(--primary-500) 22%, transparent);
        animation: bulk-nav-glow 2.6s ease-in-out 3;
    }

    .fi-sidebar-item > a[href$="/admin/bulk-upload"]:hover {
        background: linear-gradient(90deg, color-mix(in oklab, var(--primary-500) 24%, transparent), color-mix(in oklab, var(--primary-500) 8%, transparent));
        box-shadow: inset 0 0 0 1px color-mix(in oklab, var(--primary-500) 35%, transparent);
    }

    .fi-sidebar-item > a[href$="/admin/bulk-upload"]::before {
        content: "";
        position: absolute;
        left: 0;
        top: 50%;
        width: 3px;
        height: 58%;
        transform: translateY(-50%);
        border-radius: 999px;
        background: linear-gradient(180deg, var(--primary-400), var(--primary-600));
    }

    .fi-sidebar-item > a[href$="/admin/bulk-upload"] .fi-sidebar-item-icon {
        color: var(--primary-600);
    }

    .fi-sidebar-item.fi-active > a[href$="/admin/bulk-upload"] {
        animation: none;
    }

    @keyframes bulk-nav-glow {
        0%, 100% { box-shadow: inset 0 0 0 1px color-mix(in oklab, var(--primary-500) 22%, transparent); }
        50% { box-shadow: inset 0 0 0 1px color-mix(in oklab, var(--primary-500) 50%, transparent), 0 0 0 4px color-mix(in oklab, var(--primary-500) 12%, transparent); }
    }

    @media (prefers-reduced-motion: reduce) {
        .fi-sidebar-item > a[href$="/admin/bulk-upload"] { animation: none; }
    }
</style>
