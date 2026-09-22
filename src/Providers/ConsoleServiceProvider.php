<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Providers;

use Composer\InstalledVersions;
use Illuminate\Foundation\Console\AboutCommand;
use Misaf\VendraConsole\Auth\ConsolePanelAccessResolver;
use Misaf\VendraConsole\Console\Commands\CreateConsoleUserCommand;
use Misaf\VendraConsole\Console\Commands\GrantConsoleAccessCommand;
use Misaf\VendraConsole\Console\Commands\IssueConsolePasswordCommand;
use Misaf\VendraConsole\Console\Commands\RevokeConsoleUserCommand;
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
            ->hasConfigFile()
            ->hasTranslations()
            ->hasMigrations([
                'create_consoles_table',
            ])
            ->hasCommands([
                CreateConsoleUserCommand::class,
                IssueConsolePasswordCommand::class,
                GrantConsoleAccessCommand::class,
                RevokeConsoleUserCommand::class,
            ])
            ->hasInstallCommand(function (InstallCommand $command): void {
                $command
                    ->askToStarRepoOnGitHub('misaf/vendra-console');
            });
    }

    public function packageBooted(): void
    {
        AboutCommand::add('Vendra Console', fn (): array => [
            'Version' => InstalledVersions::getPrettyVersion('misaf/vendra-console'),
        ]);

        $this->app->make(PanelAccessRegistry::class)->register(new ConsolePanelAccessResolver);
    }
}
