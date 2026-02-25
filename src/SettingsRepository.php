<?php
declare(strict_types=1);
namespace Bambamboole\FilamentSettings;

use Bambamboole\FilamentSettings\Models\Setting;
use Closure;
use Illuminate\Support\Facades\Cache;

final class SettingsRepository
{
    private ?Closure $tenantResolver = null;

    /**
     * Get a setting value with group-level casting applied.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $raw = $this->raw($key);

        if ($raw === null) {
            return $default;
        }

        $cast = $this->getCast($key);

        return match ($cast) {
            'boolean' => filter_var($raw, FILTER_VALIDATE_BOOLEAN),
            'integer' => (int) $raw,
            default => $raw,
        };
    }

    /**
     * Persist a setting value to the database and bust the cache.
     */
    public function set(string $key, mixed $value): void
    {
        if ($value === null || $value === '') {
            Setting::query()->where('key', $key)->delete();
            Cache::forget($this->tenantCacheKey());
        } else {
            $serialized = is_bool($value) ? ($value ? '1' : '0') : (string) $value;

            Setting::query()->updateOrCreate(
                ['key' => $key, 'team_id' => $this->resolveTenantId()],
                ['value' => $serialized],
            );
        }
    }

    /**
     * Get a setting value cast to boolean.
     */
    public function bool(string $key, bool $default = false): bool
    {
        $raw = $this->raw($key);

        if ($raw === null) {
            return $default;
        }

        return filter_var($raw, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Get a setting value cast to integer.
     */
    public function int(string $key, int $default = 0): int
    {
        $raw = $this->raw($key);

        if ($raw === null) {
            return $default;
        }

        return (int) $raw;
    }

    /**
     * Get a setting value cast to string.
     */
    public function string(string $key, string $default = ''): string
    {
        return $this->raw($key) ?? $default;
    }

    /**
     * Get a setting value cast to array (JSON-decoded).
     *
     * @param  array<mixed>  $default
     * @return array<mixed>
     */
    public function array(string $key, array $default = []): array
    {
        $raw = $this->raw($key);

        if ($raw === null) {
            return $default;
        }

        $decoded = json_decode((string) $raw, true);

        return is_array($decoded) ? $decoded : $default;
    }

    /**
     * Resolve the cast type for a setting key, or null if none is defined.
     */
    public function getCast(string $key): ?string
    {
        if (!$this->cacheEnabled()) {
            return $this->resolveAllCasts()[$key] ?? null;
        }

        $casts = Cache::remember(
            $this->castsCacheKey(),
            $this->cacheTtl(),
            fn (): array => $this->resolveAllCasts(),
        );

        return $casts[$key] ?? null;
    }

    public function setTenantResolver(Closure $resolver): void
    {
        $this->tenantResolver = $resolver;
    }

    public function resolveTenantId(): mixed
    {
        if ($this->tenantResolver instanceof \Closure) {
            return ($this->tenantResolver)();
        }

        return \Filament\Facades\Filament::getTenant()?->getKey();
    }

    /**
     * Cache key for all settings belonging to the current tenant.
     */
    public function tenantCacheKey(): string
    {
        $tenantId = $this->resolveTenantId();

        return $tenantId !== null ? "settings.{$tenantId}" : 'settings.global';
    }

    /**
     * Cache key for the combined casts map across all registered groups.
     */
    public function castsCacheKey(): string
    {
        return 'settings.casts';
    }

    /**
     * Read the raw string value from the database, with optional caching.
     */
    private function raw(string $key): mixed
    {
        if (!$this->cacheEnabled()) {
            return Setting::query()->where('key', $key)->value('value');
        }

        $all = Cache::remember(
            $this->tenantCacheKey(),
            $this->cacheTtl(),
            static fn (): array => Setting::query()->pluck('value', 'key')->all(),
        );

        return $all[$key] ?? null;
    }

    /**
     * Build the complete casts map from all registered SettingGroup classes.
     *
     * @return array<string, string>
     */
    private function resolveAllCasts(): array
    {
        $casts = [];

        foreach ($this->resolveGroupClasses() as $groupClass) {
            $groupKey = $groupClass::key();

            foreach ((new $groupClass)->casts() as $fieldName => $castType) {
                $settingKey = SettingGroup::toSettingKey($fieldName);
                $casts["{$groupKey}.{$settingKey}"] = $castType;
            }
        }

        return $casts;
    }

    /**
     * @return array<class-string<SettingGroup>>
     */
    private function resolveGroupClasses(): array
    {
        try {
            return FilamentSettingsPlugin::get()->getGroups();
        } catch (\Throwable) {
            return config('filament-settings.groups', []);
        }
    }

    private function cacheEnabled(): bool
    {
        return (bool) config('filament-settings.cache.enabled', true);
    }

    private function cacheTtl(): int
    {
        return (int) config('filament-settings.cache.ttl', 3600);
    }
}
