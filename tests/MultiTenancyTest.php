<?php
declare(strict_types=1);

use Bambamboole\FilamentSettings\Models\Setting;
use Bambamboole\FilamentSettings\Tests\Fixtures\TestGeneralSettings;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    Cache::flush();
});

// --- Global scope / isolation ---

it('scopes reads to null tenant when no resolver is set', function () {
    Setting::query()->withoutGlobalScope('tenant')->create([
        'key' => 'general.site-name',
        'value' => 'Tenant Site',
        'team_id' => 1,
    ]);

    expect(Setting::all())->toHaveCount(0);
});

it('scopes reads to the active tenant', function () {
    Setting::query()->withoutGlobalScope('tenant')->create([
        'key' => 'general.site-name',
        'value' => 'Tenant 1 Site',
        'team_id' => 1,
    ]);
    Setting::query()->withoutGlobalScope('tenant')->create([
        'key' => 'general.site-name',
        'value' => 'Tenant 2 Site',
        'team_id' => 2,
    ]);

    setTenant(1);

    $results = Setting::all();
    expect($results)->toHaveCount(1);
    expect($results->first()->value)->toBe('Tenant 1 Site');
});

it('treats null tenant as distinct from any tenant', function () {
    Setting::query()->withoutGlobalScope('tenant')->create([
        'key' => 'general.site-name',
        'value' => 'Global Site',
        'team_id' => null,
    ]);

    setTenant(1);

    expect(Setting::all())->toHaveCount(0);
});

// --- SettingGroup::save / load ---

it('saves a setting to the active tenant', function () {
    setTenant(1);

    $group = new TestGeneralSettings;
    $group->save(['site_name' => 'Tenant Site']);

    $row = Setting::query()->withoutGlobalScope('tenant')
        ->where('key', 'general.site-name')
        ->first();

    expect($row)->not->toBeNull();
    expect($row->team_id)->toBe(1);
    expect($row->value)->toBe('Tenant Site');
});

it('does not overwrite another tenants setting', function () {
    Setting::query()->withoutGlobalScope('tenant')->create([
        'key' => 'general.site-name',
        'value' => 'Tenant 2 Site',
        'team_id' => 2,
    ]);

    setTenant(1);

    $group = new TestGeneralSettings;
    $group->save(['site_name' => 'Tenant 1 Site']);

    $count = Setting::query()->withoutGlobalScope('tenant')
        ->where('key', 'general.site-name')
        ->count();

    expect($count)->toBe(2);
});

it('loads only the active tenants settings', function () {
    Setting::query()->withoutGlobalScope('tenant')->create([
        'key' => 'general.site-name',
        'value' => 'Tenant 1 Site',
        'team_id' => 1,
    ]);
    Setting::query()->withoutGlobalScope('tenant')->create([
        'key' => 'general.site-name',
        'value' => 'Global Site',
        'team_id' => null,
    ]);

    setTenant(1);

    $state = (new TestGeneralSettings)->load();

    expect($state['site_name'])->toBe('Tenant 1 Site');
});

// --- SettingsRepository::set / get ---

it('persists a setting scoped to the active tenant', function () {
    setTenant(1);

    settings()->set('general.site-name', 'Tenant Site');

    $row = Setting::query()->withoutGlobalScope('tenant')
        ->where('key', 'general.site-name')
        ->first();

    expect($row)->not->toBeNull();
    expect($row->team_id)->toBe(1);
});

it('retrieves only the active tenants value', function () {
    Setting::query()->withoutGlobalScope('tenant')->create([
        'key' => 'general.site-name',
        'value' => 'Tenant 1 Site',
        'team_id' => 1,
    ]);
    Setting::query()->withoutGlobalScope('tenant')->create([
        'key' => 'general.site-name',
        'value' => 'Tenant 2 Site',
        'team_id' => 2,
    ]);

    setTenant(1);

    expect(settings('general.site-name'))->toBe('Tenant 1 Site');
});

it('returns null when no value exists for the active tenant', function () {
    Setting::query()->withoutGlobalScope('tenant')->create([
        'key' => 'general.site-name',
        'value' => 'Tenant 2 Site',
        'team_id' => 2,
    ]);

    setTenant(1);

    expect(settings('general.site-name'))->toBeNull();
});

// --- Cache isolation ---

it('uses tenant-scoped cache keys', function () {
    Setting::query()->withoutGlobalScope('tenant')->create([
        'key' => 'general.site-name',
        'value' => 'Tenant 1 Site',
        'team_id' => 1,
    ]);

    setTenant(1);

    settings('general.site-name');

    expect(Cache::has('settings.1'))->toBeTrue();
    expect(Cache::has('settings.global'))->toBeFalse();
});

it('clears the tenant-scoped cache key on save', function () {
    Setting::query()->withoutGlobalScope('tenant')->create([
        'key' => 'general.site-name',
        'value' => 'Tenant 1 Site',
        'team_id' => 1,
    ]);
    Setting::query()->withoutGlobalScope('tenant')->create([
        'key' => 'general.site-name',
        'value' => 'Tenant 2 Site',
        'team_id' => 2,
    ]);

    setTenant(1);
    settings('general.site-name'); // prime tenant 1 cache

    setTenant(2);
    settings('general.site-name'); // prime tenant 2 cache

    expect(Cache::has('settings.1'))->toBeTrue();
    expect(Cache::has('settings.2'))->toBeTrue();

    // Update tenant 1's setting — only tenant 1 cache bucket should be cleared
    setTenant(1);
    settings()->set('general.site-name', 'Updated');

    expect(Cache::has('settings.1'))->toBeFalse();
    expect(Cache::has('settings.2'))->toBeTrue();
});

it('uses non-tenant cache key when no resolver is configured', function () {
    // Tenant mode is enabled (TenantTestCase) but no resolver is set.
    // resolveTenantId() returns null, so the cache key should be the global format.
    Setting::query()->withoutGlobalScope('tenant')->create([
        'key' => 'general.site-name',
        'value' => 'My Site',
        'team_id' => null,
    ]);

    settings('general.site-name');

    expect(Cache::has('settings.global'))->toBeTrue();
});
