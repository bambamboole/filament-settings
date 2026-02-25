<?php
declare(strict_types=1);
namespace Bambamboole\FilamentSettings\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static mixed get(string $key, mixed $default = null)
 * @method static void set(string $key, mixed $value)
 * @method static bool bool(string $key, bool $default = false)
 * @method static int int(string $key, int $default = 0)
 * @method static string string(string $key, string $default = '')
 * @method static array array(string $key, array $default = [])
 * @method static ?string getCast(string $key)
 *
 * @see \Bambamboole\FilamentSettings\SettingsRepository
 */
class Settings extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Bambamboole\FilamentSettings\SettingsRepository::class;
    }
}
