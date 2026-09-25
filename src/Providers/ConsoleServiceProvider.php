<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Providers;

use Composer\InstalledVersions;
use Illuminate\Foundation\Console\AboutCommand;
use Misaf\VendraConsole\Auth\ConsolePanelAccessResolver;
use Misaf\VendraConsole\Console\Commands\CreateConsoleUserCommand;
use Misaf\VendraConsole\Console\Commands\GrantConsoleAccessCommand;
use Misaf\VendraConsole\Console\Commands\IssueConsolePasswordCommand;
use Misaf\VendraConsole\Console\Commands\ResetConsoleTwoFactorCommand;
use Misaf\VendraConsole\Console\Commands\RevokeConsoleUserCommand;
use Misaf\VendraConsole\Settings\BillingSettings;
use Misaf\VendraConsole\Settings\ConsoleSettings;
use Misaf\VendraConsole\Support\SettingsBillingProfile;
use Misaf\VendraSubscription\Contracts\BillingProfile;
use Misaf\VendraSupport\Settings\RegistersSettings;
use Misaf\VendraUser\Support\PanelAccessRegistry;
use Spatie\LaravelPackageTools\Commands\InstallCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

final class ConsoleServiceProvider extends PackageServiceProvider
{
    use RegistersSettings;

    public function configurePackage(Package $package): void
    {
        $package
            ->name('vendra-console')
            ->hasConfigFile()
            ->hasTranslations()
            ->hasMigrations([
                'create_consoles_table',
            ])
            ->hasConsoleCommands([
                CreateConsoleUserCommand::class,
                IssueConsolePasswordCommand::class,
                GrantConsoleAccessCommand::class,
                RevokeConsoleUserCommand::class,
                ResetConsoleTwoFactorCommand::class,
            ])
            ->hasInstallCommand(function (InstallCommand $command): void {
                $command
                    ->askToStarRepoOnGitHub('misaf/vendra-console');
            });
    }

    public function packageRegistered(): void
    {
        $this->registerSettings([BillingSettings::class, ConsoleSettings::class], __DIR__.'/../../database/settings');

        $this->app->singleton(BillingProfile::class, SettingsBillingProfile::class);
    }

    public function packageBooted(): void
    {
        AboutCommand::add('Vendra Console', fn (): array => [
            'Version' => InstalledVersions::getPrettyVersion('misaf/vendra-console'),
        ]);

        $this->app->make(PanelAccessRegistry::class)->register(new ConsolePanelAccessResolver);
    }
}
