<?php
declare(strict_types=1);
namespace Bambamboole\FilamentSettings;

use Bambamboole\FilamentSettings\Models\Setting;
use Filament\Schemas\Components\Component;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

abstract class SettingGroup
{
    /**
     * The unique key for this setting group (e.g. 'general', 'ai').
     */
    abstract public static function key(): string;

    /**
     * The Filament form schema components for this group.
     *
     * @return array<Component>
     */
    abstract public function schema(): array;

    /**
     * The translatable label for this group's tab.
     */
    public function label(): string
    {
        return static::key();
    }

    /**
     * The icon for this group's tab.
     */
    public function icon(): ?Heroicon
    {
        return null;
    }

    /**
     * Determine if the current user can access this setting group.
     */
    public static function canAccess(): bool
    {
        return true;
    }

    /**
     * Sort order for display (lower = first).
     */
    public function sort(): int
    {
        return 0;
    }

    /**
     * Eloquent-style casts for non-string fields.
     *
     * @return array<string, string>
     */
    public function casts(): array
    {
        return [];
    }

    /**
     * Load all settings for this group from the database.
     *
     * @return array<string, mixed>
     */
    public function load(): array
    {
        $prefix = static::key().'.';
        $state = [];
        $allSettings = app(SettingsRepository::class)->all();

        foreach ($allSettings as $dbKey => $rawValue) {
            if (!str_starts_with($dbKey, $prefix)) {
                continue;
            }

            $settingKey = Str::after($dbKey, $prefix);
            $fieldName = self::toFieldName($settingKey);
            $state[$fieldName] = $this->deserializeValue($fieldName, $rawValue);
        }

        return $state;
    }

    /**
     * Save form state to the database for this group.
     *
     * @param  array<string, mixed>  $data
     */
    public function save(array $data): void
    {
        $prefix = static::key().'.';
        $repo = app(SettingsRepository::class);

        DB::transaction(function () use ($data, $prefix, $repo): void {
            foreach ($data as $fieldName => $value) {
                $settingKey = self::toSettingKey($fieldName);
                $dbKey = $prefix.$settingKey;

                if ($value === null || $value === '') {
                    Setting::query()->where('key', $dbKey)->delete();
                } else {
                    Setting::query()->updateOrCreate(
                        ['key' => $dbKey, 'team_id' => $repo->resolveTenantId()],
                        ['value' => $this->serializeValue($fieldName, $value)],
                    );
                }
            }
        });

        Cache::forget($repo->tenantCacheKey());
        $repo->clearInMemoryCache();
    }

    /**
     * Deserialize a raw DB string to a typed PHP value using casts().
     */
    public function deserializeValue(string $fieldName, string $raw): mixed
    {
        $cast = $this->casts()[$fieldName] ?? null;

        return match ($cast) {
            'boolean' => filter_var($raw, FILTER_VALIDATE_BOOLEAN),
            'integer' => (int) $raw,
            default => $raw,
        };
    }

    /**
     * Serialize a typed PHP value to a DB string.
     */
    protected function serializeValue(string $fieldName, mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        return (string) $value;
    }

    /**
     * Convert a kebab-case setting key to a snake_case field name.
     */
    public static function toFieldName(string $settingKey): string
    {
        return Str::replace('-', '_', $settingKey);
    }

    /**
     * Convert a snake_case field name to a kebab-case setting key.
     */
    public static function toSettingKey(string $fieldName): string
    {
        return Str::replace('_', '-', $fieldName);
    }
}
