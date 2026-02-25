<?php
declare(strict_types=1);
namespace Bambamboole\FilamentSettings\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

final class Setting extends Model
{
    /** @use HasFactory<\Bambamboole\FilamentSettings\Database\Factories\SettingFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = ['key', 'value'];

    protected static function booted(): void
    {
        self::addGlobalScope('tenant', function (Builder $builder): void {
            if (!config('filament-settings.tenant.enabled', false)) {
                return;
            }

            $column = config('filament-settings.tenant.column', 'team_id');
            $tenantId = app(\Bambamboole\FilamentSettings\SettingsRepository::class)->resolveTenantId();

            if ($tenantId !== null) {
                $builder->where($column, $tenantId);
            }
        });

        self::saved(function (Setting $setting): void {
            self::clearCache($setting->key);
        });

        self::deleted(function (Setting $setting): void {
            self::clearCache($setting->key);
        });
    }

    private static function clearCache(string $key): void
    {
        Cache::forget("settings.{$key}");
        Cache::forget("settings.cast.{$key}");
    }
}
