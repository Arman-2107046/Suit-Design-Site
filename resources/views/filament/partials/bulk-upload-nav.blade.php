{{--
    The bulk uploader is the one page that writes to nearly every table, so it is
    lifted out of the palette the rest of the sidebar shares and given an indigo
    accent. The glow runs a few times on load, then settles.
--}}
<style>
    .fi-sidebar-item > a[href$="/admin/bulk-upload"] {
        position: relative;
        font-weight: 600;
        color: #4338ca;
        background: linear-gradient(90deg, rgba(99, 102, 241, 0.14), rgba(99, 102, 241, 0.04));
        box-shadow: inset 0 0 0 1px rgba(99, 102, 241, 0.22);
        animation: bulk-nav-glow 2.6s ease-in-out 3;
    }

    .fi-sidebar-item > a[href$="/admin/bulk-upload"]:hover {
        background: linear-gradient(90deg, rgba(99, 102, 241, 0.24), rgba(99, 102, 241, 0.08));
        box-shadow: inset 0 0 0 1px rgba(99, 102, 241, 0.35);
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
        background: linear-gradient(180deg, #818cf8, #6366f1);
    }

    .fi-sidebar-item > a[href$="/admin/bulk-upload"] .fi-sidebar-item-icon {
        color: #6366f1;
    }

    .fi-sidebar-item.fi-active > a[href$="/admin/bulk-upload"] {
        animation: none;
    }

    @keyframes bulk-nav-glow {
        0%, 100% { box-shadow: inset 0 0 0 1px rgba(99, 102, 241, 0.22); }
        50% { box-shadow: inset 0 0 0 1px rgba(99, 102, 241, 0.5), 0 0 0 4px rgba(99, 102, 241, 0.12); }
    }

    @media (prefers-reduced-motion: reduce) {
        .fi-sidebar-item > a[href$="/admin/bulk-upload"] { animation: none; }
    }
</style>
