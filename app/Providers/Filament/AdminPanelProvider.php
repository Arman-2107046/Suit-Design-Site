<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use App\Filament\NavigationGroups;
use App\Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
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
            ->darkMode(false)
            /* Navigating swaps the body instead of unloading the document, so a bulk
               upload in flight survives the admin moving to another menu. */
            ->spa()
            ->colors([
                'primary' => Color::Blue,
            ])
            /* Every group starts closed, so the sidebar reads as a short list of cards; a click opens one. */
            ->navigationGroups(NavigationGroups::all())
            ->renderHook(PanelsRenderHook::HEAD_END, fn (): string => view('filament.partials.sidebar-groups')->render())
            ->renderHook(PanelsRenderHook::STYLES_AFTER, fn (): string => view('filament.partials.bulk-upload-nav')->render())
            ->renderHook(PanelsRenderHook::BODY_END, fn (): string => view('filament.partials.bulk-upload-dock')->render())
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
