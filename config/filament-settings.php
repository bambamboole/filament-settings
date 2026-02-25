<?php
declare(strict_types=1);

return [
    'groups' => [
        // Register your SettingGroup classes here, or use the plugin's groups() method.
    ],

    'cache' => [
        'enabled' => env('SETTINGS_CACHE_ENABLED', true),
        'ttl' => env('SETTINGS_CACHE_TTL', 3600),
    ],

    'tenant' => [
        'enabled' => false,
        'column' => 'team_id',
    ],
];
