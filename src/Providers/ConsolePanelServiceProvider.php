<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Providers;

use Filament\FontProviders\SpatieGoogleFontProvider;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Enums\Width;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Uri;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Misaf\VendraLocalization\Http\Middleware\SetLocale;
use Misaf\VendraSupport\Http\Middleware\AddPanelToRequestJobContext;

/**
 * The console (platform admin) panel.
 *
 * Runs outside the tenant middleware stack so a console operator can manage
 * resellers, plans, subscriptions, and stores across every tenant.
 */
final class ConsolePanelServiceProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('console')
            ->brandLogo(fn(): string => asset('images/vendra-logo.svg'))
            ->brandLogoHeight('2rem')
            /*
             | Resolved per request rather than at boot: the brand is an
             | operator-editable setting, and reading it here means a rename
             | shows up on the next page load instead of the next deploy.
             */
            ->brandName(fn(): string => Config::string('console.platform.name'))
            ->darkModeBrandLogo(fn(): string => asset('images/vendra-logo-dark.svg'))
            ->databaseNotifications()
            ->databaseTransactions()
            ->discoverResources(__DIR__ . '/../Filament/Resources', 'Misaf\\VendraConsole\\Filament\\Resources')
            ->discoverPages(__DIR__ . '/../Filament/Pages', 'Misaf\\VendraConsole\\Filament\\Pages')
            ->discoverWidgets(__DIR__ . '/../Filament/Widgets', 'Misaf\\VendraConsole\\Filament\\Widgets')
            ->pages([Dashboard::class])
            ->globalSearchFieldKeyBindingSuffix()
            ->globalSearchKeyBindings(['command+k', 'ctrl+k'])
            ->homeUrl('/')
            ->authGuard('console')
            ->authPasswordBroker('console_users')
            ->domain('console.' . Uri::of(config()->string('app.url'))->host())
            ->login()
            ->passwordReset()
            ->emailVerification(isRequired: true)
            ->maxContentWidth(Width::Full)
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                AddPanelToRequestJobContext::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
                SetLocale::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ])
            ->font(
                fn(): string => app()->isLocale('fa') ? 'Vazirmatn' : 'Google',
                provider: SpatieGoogleFontProvider::class,
            )
            ->path('')
            ->profile()
            ->topNavigation();
    }
}
