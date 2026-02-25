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
        } else {
            $serialized = is_bool($value) ? ($value ? '1' : '0') : (string) $value;

            Setting::query()->updateOrCreate(
                ['key' => $key],
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

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : $default;
    }

    /**
     * Resolve the cast type for a setting key, or null if none is defined.
     */
    public function getCast(string $key): ?string
    {
        if (!$this->cacheEnabled()) {
            return $this->resolveCast($key);
        }

        return Cache::remember(
            $this->cacheKey("cast.{$key}"),
            $this->cacheTtl(),
            fn (): ?string => $this->resolveCast($key),
        );
    }

    public function setTenantResolver(Closure $resolver): void
    {
        $this->tenantResolver = $resolver;
    }

    public function resolveTenantId(): mixed
    {
        if (!$this->tenantResolver instanceof \Closure) {
            return null;
        }

        return ($this->tenantResolver)();
    }

    /**
     * Read the raw string value from the database, with optional caching.
     */
    private function raw(string $key): ?string
    {
        if (!$this->cacheEnabled()) {
            return Setting::query()->where('key', $key)->value('value');
        }

        return Cache::remember(
            $this->cacheKey($key),
            $this->cacheTtl(),
            static fn (): mixed => Setting::query()->where('key', $key)->value('value'),
        );
    }

    /**
     * Look up the cast type from the matching SettingGroup's casts().
     */
    private function resolveCast(string $key): ?string
    {
        $groupKey = strstr($key, '.', true) ?: $key;
        $fieldName = SettingGroup::toFieldName($this->settingKeyFrom($key));

        foreach ($this->resolveGroupClasses() as $groupClass) {
            if ($groupClass::key() === $groupKey) {
                return (new $groupClass)->casts()[$fieldName] ?? null;
            }
        }

        return null;
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

    private function cacheKey(string $key): string
    {
        if (config('filament-settings.tenant.enabled', false) && $this->tenantResolver instanceof \Closure) {
            $tenantId = $this->resolveTenantId() ?? 'global';

            return "settings.{$tenantId}.{$key}";
        }

        return "settings.{$key}";
    }

    private function cacheEnabled(): bool
    {
        return (bool) config('filament-settings.cache.enabled', true);
    }

    private function cacheTtl(): int
    {
        return (int) config('filament-settings.cache.ttl', 3600);
    }

    /**
     * Extract the setting key portion after the group prefix.
     */
    private function settingKeyFrom(string $key): string
    {
        $pos = strpos($key, '.');

        return $pos !== false ? substr($key, $pos + 1) : $key;
    }
}
