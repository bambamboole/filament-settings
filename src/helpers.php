<?php
declare(strict_types=1);

use Bambamboole\FilamentSettings\SettingsRepository;

if (!function_exists('settings')) {
    /**
     * Get a setting value, or the repository instance when called without arguments.
     *
     * @return ($key is null ? SettingsRepository : mixed)
     */
    function settings(?string $key = null, mixed $default = null): mixed
    {
        $repository = app(SettingsRepository::class);

        if ($key === null) {
            return $repository;
        }

        return $repository->get($key, $default);
    }
}
