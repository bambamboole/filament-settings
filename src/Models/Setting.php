<?php
declare(strict_types=1);
namespace Bambamboole\FilamentSettings\Models;

use Bambamboole\FilamentSettings\Database\Factories\SettingFactory;
use Bambamboole\FilamentSettings\SettingsRepository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Override;

/**
 * @property string $key
 */
final class Setting extends Model
{
    /** @use HasFactory<SettingFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $guarded = [];

    #[Override]
    protected static function booted(): void
    {
        self::addGlobalScope('tenant', function (Builder $builder): void {
            $tenantId = app(SettingsRepository::class)->resolveTenantId();

            $builder->where('team_id', $tenantId);
        });

        self::saved(function (Setting $setting): void {
            self::clearCache();
        });

        self::deleted(function (Setting $setting): void {
            self::clearCache();
        });
    }

    private static function clearCache(): void
    {
        $repo = app(SettingsRepository::class);
        Cache::forget($repo->tenantCacheKey());
    }
}
