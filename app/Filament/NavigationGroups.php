<?php

namespace App\Filament;

use Filament\Navigation\NavigationGroup;

/*
 * The sidebar's groups, in order, each with its icon.
 *
 * Filament lets a group or its pages carry icons, never both, and the pages
 * keep theirs. So the groups are registered without one, and their icons are
 * drawn beside the group name by resources/views/filament/partials/sidebar-groups.
 */
final class NavigationGroups
{
    public const ICONS = [
        'Sales' => 'heroicon-o-shopping-bag',
        'Fabrics' => 'heroicon-o-swatch',
        'Jacket' => 'heroicon-o-scissors',
        'Lapels' => 'heroicon-o-tag',
        'Pockets' => 'heroicon-o-wallet',
        'Linings' => 'heroicon-o-rectangle-stack',
        'Journal' => 'heroicon-o-book-open',
        'Content' => 'heroicon-o-document-text',
        'Support' => 'heroicon-o-lifebuoy',
        'Team' => 'heroicon-o-user-group',
    ];

    /** @return list<NavigationGroup> every group, closed until clicked */
    public static function all(): array
    {
        return array_map(
            fn (string $label) => NavigationGroup::make($label)->collapsed(),
            array_keys(self::ICONS),
        );
    }
}
