<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Providers;

use Composer\InstalledVersions;
use Illuminate\Foundation\Console\AboutCommand;
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
    }
}
