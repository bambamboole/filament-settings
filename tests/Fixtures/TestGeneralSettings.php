<?php
declare(strict_types=1);
namespace Bambamboole\FilamentSettings\Tests\Fixtures;

use Bambamboole\FilamentSettings\SettingGroup;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Support\Icons\Heroicon;

final class TestGeneralSettings extends SettingGroup
{
    public static function key(): string
    {
        return 'general';
    }

    public function label(): string
    {
        return 'General';
    }

    public function icon(): Heroicon
    {
        return Heroicon::OutlinedCog6Tooth;
    }

    public function casts(): array
    {
        return ['launched' => 'boolean'];
    }

    public function schema(): array
    {
        return [
            Toggle::make('launched')
                ->label('Launched')
                ->default(false),
            TextInput::make('site_name')
                ->label('Site Name')
                ->maxLength(255),
        ];
    }
}
