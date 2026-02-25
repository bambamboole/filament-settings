<?php
declare(strict_types=1);
namespace Bambamboole\FilamentSettings;

use Spatie\LaravelPackageTools\Commands\InstallCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class FilamentSettingsServiceProvider extends PackageServiceProvider
{
    public static string $name = 'filament-settings';

    public static string $viewNamespace = 'filament-settings';

    public function configurePackage(Package $package): void
    {
        $package->name(static::$name)
            ->hasConfigFile()
            ->hasViews(static::$viewNamespace)
            ->hasTranslations()
            ->hasMigrations(['create_settings_table'])
            ->hasInstallCommand(function (InstallCommand $command): void {
                $command
                    ->publishConfigFile()
                    ->publishMigrations()
                    ->askToRunMigrations();
            });
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(SettingsRepository::class);
    }

    public function packageBooted(): void
    {
        //
    }
}
