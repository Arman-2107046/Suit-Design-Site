<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use App\Filament\NavigationGroups;
use App\Filament\Support\AdminSearchProvider;
use App\Filament\Support\ImageViews;
use Filament\Tables\View\TablesRenderHook;
use App\Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\View\PanelsRenderHook;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    /**
     * The brand navy, shade by shade. Filament's generator evens out lightness,
     * which turned this into a bright mid-blue, so the shades are set by hand:
     * 600 is what buttons and active items wear.
     */
    private const NAVY = [
        50 => 'oklch(0.97 0.012 262)',
        100 => 'oklch(0.94 0.026 262)',
        200 => 'oklch(0.88 0.046 262)',
        300 => 'oklch(0.79 0.072 262)',
        400 => 'oklch(0.67 0.1 262)',
        500 => 'oklch(0.55 0.12 262)',
        600 => 'oklch(0.44 0.12 262)',
        700 => 'oklch(0.38 0.11 262)',
        800 => 'oklch(0.32 0.092 262)',
        900 => 'oklch(0.27 0.075 262)',
        950 => 'oklch(0.21 0.055 262)',
    ];

    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            /* Administrators sign in against the admins table only; customer accounts cannot. */
            ->authGuard('admin')
            ->authPasswordBroker('admins')
            ->login()
            /* Light, dark or the system setting: each admin picks from the user menu */
            ->darkMode(true)
            /* The bell: new orders, messages, swatch requests, failed card payments, RankYak articles */
            ->databaseNotifications()
            ->databaseNotificationsPolling('30s')
            /* Search everything from anywhere: Ctrl+K on Windows, Cmd+K on a Mac */
            ->globalSearch(AdminSearchProvider::class)
            ->globalSearchKeyBindings(['ctrl+k', 'command+k'])
            ->globalSearchFieldKeyBindingSuffix()
            /* Navigating swaps the body instead of unloading the document, so a bulk
               upload in flight survives the admin moving to another menu. */
            ->spa()
            /* A download is not a page: SPA navigation would try to show the PDF instead of saving it. */
            ->spaUrlExceptions(['*/admin/bulk-upload/naming-guide'])
            /* The brand: navy, Figtree (the shop's own font), and the CT monogram */
            ->colors([
                'primary' => self::NAVY,
            ])
            ->font('Figtree')
            ->brandName('Custom Tailor')
            ->brandLogo(fn () => view('filament.partials.brand-logo'))
            ->brandLogoHeight('2.1rem')
            ->favicon(asset('favicon.svg'))
            /* Every group starts closed, so the sidebar reads as a short list of cards; a click opens one. */
            ->navigationGroups(NavigationGroups::all())
            ->renderHook(PanelsRenderHook::HEAD_END, fn (): string => view('filament.partials.sidebar-groups')->render())
            ->renderHook(PanelsRenderHook::HEAD_END, fn (): string => view('filament.partials.swatch-cards')->render())
            ->renderHook(PanelsRenderHook::HEAD_END, fn (): string => view('filament.partials.admin-theme')->render())
            ->renderHook(PanelsRenderHook::STYLES_AFTER, fn (): string => view('filament.partials.bulk-upload-nav')->render())
            ->renderHook(PanelsRenderHook::BODY_END, fn (): string => view('filament.partials.bulk-upload-dock')->render())
            /* Lists with pictures: show them as a grid of cards or as rows */
            ->renderHook(TablesRenderHook::TOOLBAR_START, fn (array $scopes): string => ImageViews::switcher($scopes))
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                //
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            /* Persistent, so Livewire's own requests are also checked against the admin guard. */
            ->authMiddleware([
                Authenticate::class,
            ], isPersistent: true);
    }
}
