<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Providers;

use Composer\InstalledVersions;
use Illuminate\Foundation\Console\AboutCommand;
use Illuminate\Support\Facades\Config;
use Misaf\VendraConsole\Auth\ConsolePanelAccessResolver;
use Misaf\VendraUser\Support\PanelAccessRegistry;
use Spatie\LaravelPackageTools\Commands\InstallCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

final class ConsoleServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('vendra-console')
            ->hasConfigFile('console')
            ->hasMigrations([
                'create_console_users_table',
            ])
            ->hasInstallCommand(function (InstallCommand $command): void {
                $command
                    ->publishConfigFile()
                    ->askToStarRepoOnGitHub('misaf/vendra-console');
            });
    }

    public function packageBooted(): void
    {
        AboutCommand::add('Vendra Console', fn (): array => [
            'Version' => InstalledVersions::getPrettyVersion('misaf/vendra-console'),
        ]);

        /*
        | Console authorization lives here: the resolver gates panel entry
        | on the console_users row, and the console guard resolves only
        | platform identities (tenant_id IS NULL) through the console
        | provider, so a tenant row can never win an email lookup.
        */
        $this->app->make(PanelAccessRegistry::class)->register(new ConsolePanelAccessResolver);

        $this->useConsoleUserProvider();
    }

    /**
     * Point the console guard at the console user provider, which resolves
     * platform identities only, so a tenant row sharing an email can never
     * win a lookup. The provider and its password broker are declared in
     * `config/auth.php`.
     */
    private function useConsoleUserProvider(): void
    {
        Config::set('auth.guards.console.provider', 'console');
    }
}
