{{--
    The sidebar as cards, in one quiet slate palette. Each group is a card with
    an icon chip and stays closed until clicked; its pages open beneath it as
    smaller cards with icon chips of their own. Where you are is marked in the panel's blue:
    a blue chip with a white icon, nothing louder.

    Filament lets a group or its pages have icons, not both. The pages keep
    real Filament icons; the group icons (App\Filament\NavigationGroups) are
    drawn here, as images on the chip.
--}}
@php
    /* The group's icon in a given ink, as an image the chip can show */
    $chipIcon = function (string $icon, string $ink): string {
        $svg = svg($icon)->toHtml();
        $svg = str_replace(['currentColor', 'stroke-width="1.5"'], [$ink, 'stroke-width="1.7"'], $svg);
        if (! str_contains($svg, 'xmlns=')) {
            $svg = str_replace('<svg', '<svg xmlns="http://www.w3.org/2000/svg"', $svg);
        }

        return 'url("data:image/svg+xml;base64,' . base64_encode($svg) . '")';
    };
@endphp
<style>
    .fi-sidebar-nav {
        --nav-ink: #2563eb;          /* the one strong colour, where you are: the panel's own blue */
        --nav-ink-text: #1d4ed8;
        --nav-text: #334155;
        --nav-muted: #64748b;
        --nav-line: #e2e8f0;
        --nav-chip: #f1f5f9;
        --nav-chip-hover: #e2e8f0;
    }

    .fi-sidebar-nav-groups { row-gap: 6px; }

    @foreach (\App\Filament\NavigationGroups::ICONS as $label => $icon)
        .fi-sidebar-group[data-group-label="{{ $label }}"] {
            --grp-icon: {!! $chipIcon($icon, '#475569') !!};
            --grp-icon-active: {!! $chipIcon($icon, '#ffffff') !!};
            --grp-icon-dark: {!! $chipIcon($icon, '#d4d4d8') !!};
        }
    @endforeach

    /* ── The group card ─────────────────────────────────────────── */
    .fi-sidebar-group-btn {
        position: relative;
        gap: 10px;
        padding: 7px 8px;
        border-radius: 12px;
        cursor: pointer;
        background: #fff;
        box-shadow: inset 0 0 0 1px var(--nav-line), 0 1px 2px rgba(15, 23, 42, 0.04);
        transition: box-shadow 0.18s ease, transform 0.18s ease;
    }
    .fi-sidebar-group-btn:hover {
        box-shadow: inset 0 0 0 1px #cbd5e1, 0 4px 12px -6px rgba(15, 23, 42, 0.16);
        transform: translateY(-1px);
    }

    /* The icon chip, ahead of the name */
    .fi-sidebar-group-label { display: flex; align-items: center; gap: 10px; font-weight: 600; color: var(--nav-text); }
    .fi-sidebar-group-label::before {
        content: "";
        flex-shrink: 0;
        width: 30px;
        height: 30px;
        border-radius: 9px;
        background: var(--grp-icon) center / 16px no-repeat, var(--nav-chip);
        box-shadow: inset 0 0 0 1px var(--nav-line);
        transition: background-color 0.18s ease;
    }
    .fi-sidebar-group-btn:hover .fi-sidebar-group-label::before { background-color: var(--nav-chip-hover); }

    /* Open: a shade deeper, so it reads as the parent of what is below */
    .fi-sidebar-group:not(.fi-collapsed) > .fi-sidebar-group-btn {
        background: linear-gradient(180deg, #fff, #f8fafc);
        box-shadow: inset 0 0 0 1px #cbd5e1, 0 6px 16px -10px rgba(15, 23, 42, 0.22);
    }

    /* The group you are in: a blue chip and a thin bar, as Bulk Upload has */
    .fi-sidebar-group.fi-active .fi-sidebar-group-label { color: var(--nav-ink-text); }
    .fi-sidebar-group.fi-active .fi-sidebar-group-label::before {
        background: var(--grp-icon-active) center / 16px no-repeat, var(--nav-ink);
        box-shadow: 0 4px 10px -4px rgba(37, 99, 235, 0.45);
    }
    .fi-sidebar-group.fi-active > .fi-sidebar-group-btn::before {
        content: "";
        position: absolute;
        left: 0;
        top: 50%;
        width: 3px;
        height: 52%;
        transform: translateY(-50%);
        border-radius: 999px;
        background: var(--nav-ink);
    }

    /* ── The pages inside: smaller cards on a rail ──────────────── */
    .fi-sidebar-group:has(> .fi-sidebar-group-btn) > .fi-sidebar-group-items {
        gap: 4px;
        margin: 4px 0 8px 14px;
        padding-left: 10px;
        border-left: 1px solid var(--nav-line);
    }

    .fi-sidebar-group:has(> .fi-sidebar-group-btn) .fi-sidebar-item-btn {
        justify-content: flex-start;
        gap: 10px;
        padding: 6px 8px;
        border-radius: 10px;
        background: #fff;
        box-shadow: inset 0 0 0 1px #eef2f6;
        transition: box-shadow 0.15s ease, transform 0.15s ease;
    }
    .fi-sidebar-group:has(> .fi-sidebar-group-btn) .fi-sidebar-item-btn:hover {
        background: #fff;
        transform: translateX(2px);
        box-shadow: inset 0 0 0 1px #cbd5e1, 0 4px 12px -6px rgba(15, 23, 42, 0.16);
    }

    .fi-sidebar-group:has(> .fi-sidebar-group-btn) .fi-sidebar-item-btn > .fi-icon {
        box-sizing: border-box;
        flex-shrink: 0;
        width: 26px;
        height: 26px;
        padding: 5px;
        border-radius: 8px;
        color: var(--nav-muted);
        background: var(--nav-chip);
        transition: background 0.15s ease, color 0.15s ease;
    }
    .fi-sidebar-group:has(> .fi-sidebar-group-btn) .fi-sidebar-item-btn:hover > .fi-icon {
        color: var(--nav-text);
        background: var(--nav-chip-hover);
    }
    .fi-sidebar-group:has(> .fi-sidebar-group-btn) .fi-sidebar-item-label { font-size: 13px; color: var(--nav-text); }

    /* The page you are on: a blue chip, a blue edge, the name in full weight */
    .fi-sidebar-group:has(> .fi-sidebar-group-btn) .fi-sidebar-item.fi-active > .fi-sidebar-item-btn {
        background: #f5f9ff;
        box-shadow: inset 0 0 0 1px #bfdbfe, 0 4px 12px -8px rgba(37, 99, 235, 0.3);
    }
    .fi-sidebar-group:has(> .fi-sidebar-group-btn) .fi-sidebar-item.fi-active > .fi-sidebar-item-btn > .fi-icon {
        color: #fff;
        background: var(--nav-ink);
    }
    .fi-sidebar-group:has(> .fi-sidebar-group-btn) .fi-sidebar-item.fi-active .fi-sidebar-item-label {
        font-weight: 600;
        color: var(--nav-ink-text);
    }

    @media (prefers-reduced-motion: reduce) {
        .fi-sidebar-group-btn, .fi-sidebar-item-btn { transition: none; transform: none !important; }
    }
</style>

{{--
    Filament remembers which groups are open in localStorage, and only applies
    the panel's defaults when nothing is remembered yet. Browsers that used the
    admin before groups started closed still hold "all open", so clear that
    once; from then on, whatever the admin opens or closes is remembered.
    Runs in <head>, before the sidebar reads the stored value.
--}}
<script>
    try {
        if (localStorage.getItem('ct-sidebar-defaults') !== '1') {
            localStorage.removeItem('_x_collapsedGroups');
            localStorage.removeItem('collapsedGroups');
            localStorage.setItem('ct-sidebar-defaults', '1');
        }
    } catch (e) {
        /* Storage blocked: Filament falls back to the defaults on its own. */
    }

    /* Arriving on a page (a dashboard link, say) opens the group it lives in, so you can see where you are. */
    document.addEventListener('livewire:navigated', () => {
        const active = document.querySelector('.fi-sidebar-group.fi-active[data-group-label]');
        const sidebar = window.Alpine?.store('sidebar');

        if (active && sidebar?.groupIsCollapsed(active.dataset.groupLabel)) {
            sidebar.toggleCollapsedGroup(active.dataset.groupLabel);
        }
    });
</script>
