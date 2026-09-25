<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Providers;

use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\FontProviders\SpatieGoogleFontProvider;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Enums\Width;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Config;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Misaf\VendraConsole\Settings\ConsoleSettings;
use Misaf\VendraLocalization\Http\Middleware\SetLocale;
use Misaf\VendraSupport\Http\Middleware\AddPanelToRequestJobContext;

final class ConsolePanelServiceProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('console')
            ->brandLogo(fn (): string => asset('images/vendra-logo.svg'))
            ->brandLogoHeight('2rem')
            ->brandName(fn (): string => resolve(ConsoleSettings::class)->brand_name)
            ->darkModeBrandLogo(fn (): string => asset('images/vendra-logo-dark.svg'))
            ->databaseNotifications()
            ->databaseTransactions()
            ->discoverResources(__DIR__.'/../Filament/Resources', 'Misaf\\VendraConsole\\Filament\\Resources')
            ->discoverPages(__DIR__.'/../Filament/Pages', 'Misaf\\VendraConsole\\Filament\\Pages')
            ->discoverWidgets(__DIR__.'/../Filament/Widgets', 'Misaf\\VendraConsole\\Filament\\Widgets')
            ->globalSearchFieldKeyBindingSuffix()
            ->globalSearchKeyBindings(['command+k', 'ctrl+k'])
            ->homeUrl('/')
            ->authGuard('console')
            ->authPasswordBroker('console')
            ->domain('console.'.Config::string('vendra-tenant.central_host'))
            ->login()
            ->passwordReset()
            ->emailVerification()
            ->multiFactorAuthentication(AppAuthentication::make()->recoverable(), isRequired: true)
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
                fn (): string => app()->isLocale('fa') ? 'Vazirmatn' : 'Google',
                provider: SpatieGoogleFontProvider::class,
            )
            ->path('')
            ->profile()
            ->topNavigation();
    }
}
