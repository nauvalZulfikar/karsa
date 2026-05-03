<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class VendorPanelProvider extends PanelProvider
{
    public function register(): void
    {
        parent::register();

        FilamentView::registerRenderHook(
            PanelsRenderHook::HEAD_END,
            fn () => view('filament.pwa-head'),
            scopes: \App\Providers\Filament\VendorPanelProvider::class,
        );

        FilamentView::registerRenderHook(
            PanelsRenderHook::BODY_END,
            fn () => view('filament.pwa-body'),
            scopes: \App\Providers\Filament\VendorPanelProvider::class,
        );
    }

    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('vendor')
            ->path('vendor')
            ->brandName('Portal Vendor — DPUTR')
            ->colors(['primary' => Color::Teal])
            ->authGuard('web')
            ->login()
            ->discoverResources(
                in: app_path('Filament/Vendor/Resources'),
                for: 'App\\Filament\\Vendor\\Resources'
            )
            ->discoverPages(
                in: app_path('Filament/Vendor/Pages'),
                for: 'App\\Filament\\Vendor\\Pages'
            )
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
            ->authMiddleware([Authenticate::class]);
    }
}
