# Filament Settings

[![Latest Version on Packagist](https://img.shields.io/packagist/v/bambamboole/filament-settings.svg?style=flat-square)](https://packagist.org/packages/bamamboole/filament-settings)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/bamamboole/filament-settings/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/bamamboole/filament-settings/actions?query=workflow%3Arun-tests+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/bamamboole/filament-settings.svg?style=flat-square)](https://packagist.org/packages/bamamboole/filament-settings)

A database-driven settings plugin for [Filament](https://filamentphp.com). Settings are organised into groups, edited through a tabbed Filament page, and read anywhere via the `settings()` helper or the typed `SettingsRepository` methods.

## Installation

```bash
composer require bamamboole/filament-settings
```

Publish and run the migration:

```bash
php artisan vendor:publish --tag="filament-settings-migrations"
php artisan migrate
```

## Setup

Register the plugin in your panel provider and pass your `SettingGroup` classes:

```php
use Bamamboole\FilamentSettings\FilamentSettingsPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        ->plugins([
            FilamentSettingsPlugin::make()
                ->groups([
                    GeneralSettings::class,
                    MailSettings::class,
                ]),
        ]);
}
```

## Generating a Setting Group

Use the Artisan command to scaffold a new `SettingGroup` class interactively:

```bash
php artisan settings:make-group
```

The command asks for a class name, group key, and label, then writes the skeleton to `app/Settings/{ClassName}.php`:

```
 ┌ Class name ──────────────────────────────────────────────────┐
 │ GeneralSettings                                              │
 └──────────────────────────────────────────────────────────────┘

 ┌ Group key ───────────────────────────────────────────────────┐
 │ general                                                      │
 └──────────────────────────────────────────────────────────────┘

 ┌ Label ───────────────────────────────────────────────────────┐
 │ General                                                      │
 └──────────────────────────────────────────────────────────────┘

 ◇  Created: app/Settings/GeneralSettings.php
```

The group key is derived automatically from the class name (`GeneralSettings` → `general`, `MailNotificationSettings` → `mail-notification`). Accept the defaults or type a custom value.

After generation, open the file and fill in `schema()` with Filament form components and optionally add `casts()`, `icon()`, and `sort()`.

## Defining a Setting Group

Create a class that extends `SettingGroup`. The `key()` is used as the DB prefix and the tab identifier; `schema()` returns standard Filament form components:

```php
use Bamamboole\FilamentSettings\SettingGroup;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Support\Icons\Heroicon;

class GeneralSettings extends SettingGroup
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

    public function sort(): int
    {
        return 0;
    }

    public function casts(): array
    {
        return [
            'launched' => 'boolean',
        ];
    }

    public function schema(): array
    {
        return [
            TextInput::make('site_name')->label('Site Name')->maxLength(255),
            Toggle::make('launched')->label('Launched'),
        ];
    }
}
```

### Field name convention

Form field names use `snake_case` (e.g. `site_name`). They are stored in the database as `kebab-case` keys prefixed with the group key (e.g. `general.site-name`).

### Casts

Declare non-string fields in `casts()`. Supported types: `boolean`, `integer`. Everything else is returned as a raw string.

### Access control per group

Override `canAccess()` on a group to hide it from certain users:

```php
public static function canAccess(): bool
{
    return auth()->user()->isAdmin();
}
```

## Reading Settings

### Helper function

```php
// Get a value (returns null when not set)
$name = settings('general.site-name');

// Get with a default
$name = settings('general.site-name', 'My App');

// Access the repository directly
settings()->set('general.site-name', 'New Name');
```

### Typed repository methods

```php
settings()->bool('general.launched', false);
settings()->int('general.max-items', 10);
settings()->string('general.site-name', 'My App');
settings()->array('general.allowed-ips', []);
```

### Injecting the repository

```php
use Bamamboole\FilamentSettings\SettingsRepository;

class SomeService
{
    public function __construct(private SettingsRepository $settings) {}

    public function isLaunched(): bool
    {
        return $this->settings->bool('general.launched');
    }
}
```

## Caching

Caching is enabled by default. All settings for each tenant are loaded and cached in a single cache entry per tenant. Casts from all registered groups are cached together in one additional entry.

| Cache key          | Contains                                    |
|--------------------|---------------------------------------------|
| `settings.global`  | All settings where `team_id IS NULL`        |
| `settings.{id}`    | All settings for tenant with that ID        |
| `settings.casts`   | Combined cast map from all `SettingGroup`s  |

The tenant bucket is automatically invalidated whenever a setting is saved or deleted. Configure the TTL or disable caching in `config/filament-settings.php`:

```php
'cache' => [
    'enabled' => env('SETTINGS_CACHE_ENABLED', true),
    'ttl'     => env('SETTINGS_CACHE_TTL', 3600),
],
```

## Multi-Tenancy

All settings have a `team_id` column. A global scope filters every query to the current tenant automatically. When `team_id` is `null`, the global (non-tenant) settings are used.

Configure the active tenant via the plugin:

```php
FilamentSettingsPlugin::make()
    ->groups([...])
    ->tenant(fn () => auth()->user()->team_id),
```

When using Filament's built-in tenancy, `Filament::getTenant()` is used automatically — no manual configuration needed.

## Plugin Options

| Method                      | Description                                              |
|-----------------------------|----------------------------------------------------------|
| `groups(array)`             | `SettingGroup` classes to register                       |
| `canAccess(Closure)`        | Guard access to the entire settings page                 |
| `tenant(Closure)`           | Custom resolver for the current tenant ID                |
| `navigationSort(int)`       | Position in the sidebar (default: `99`)                  |
| `navigationGroup(?string)`  | Sidebar group label (default: none)                      |

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](.github/CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](.github/SECURITY.md) on how to report security vulnerabilities.

## Credits

- [Manuel Christlieb](https://github.com/bamamboole)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
