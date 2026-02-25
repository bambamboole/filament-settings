<?php
declare(strict_types=1);
namespace Bambamboole\FilamentSettings\Tests\Fixtures;

use Bambamboole\FilamentSettings\FilamentSettingsPlugin;
use Filament\Http\Middleware\Authenticate;
use Filament\Panel;
use Filament\PanelProvider;

class TestPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('test')
            ->path('test')
            ->login()
            ->plugins([
                FilamentSettingsPlugin::make()
                    ->groups([TestGeneralSettings::class]),
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
