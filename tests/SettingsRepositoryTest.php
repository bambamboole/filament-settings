<?php
declare(strict_types=1);

use Bambamboole\FilamentSettings\Models\Setting;
use Bambamboole\FilamentSettings\SettingsRepository;
use Bambamboole\FilamentSettings\Tests\Fixtures\TestGeneralSettings;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    Cache::flush();
});

it('returns the default when no DB value exists', function () {
    expect(settings('general.site-name'))->toBeNull();
});

it('returns the provided default when no DB value exists', function () {
    expect(settings('general.site-name', 'fallback'))->toBe('fallback');
});

it('returns the stored DB value', function () {
    Setting::query()->create(['key' => 'general.site-name', 'value' => 'My Site']);

    expect(settings('general.site-name'))->toBe('My Site');
});

it('casts boolean values via group casts', function () {
    Setting::query()->create(['key' => 'general.launched', 'value' => '1']);

    expect(settings('general.launched', false))->toBeTrue();
});

it('returns default false for boolean when no DB value', function () {
    expect(settings('general.launched', false))->toBeFalse();
});

it('caches the raw value', function () {
    Setting::query()->create(['key' => 'general.site-name', 'value' => 'Cached']);

    settings('general.site-name');

    expect(Cache::has('settings.global'))->toBeTrue();
});

it('clears cache when a setting is saved', function () {
    Setting::query()->create(['key' => 'general.site-name', 'value' => 'Old']);
    settings('general.site-name');

    expect(Cache::has('settings.global'))->toBeTrue();

    Setting::query()->where('key', 'general.site-name')->first()->update(['value' => 'New']);

    expect(Cache::has('settings.global'))->toBeFalse();
});

it('clears cache when a setting is deleted', function () {
    $setting = Setting::query()->create(['key' => 'general.site-name', 'value' => 'Old']);
    settings('general.site-name');

    expect(Cache::has('settings.global'))->toBeTrue();

    $setting->delete();

    expect(Cache::has('settings.global'))->toBeFalse();
});

it('respects cache.enabled config', function () {
    config()->set('filament-settings.cache.enabled', false);

    Setting::query()->create(['key' => 'general.site-name', 'value' => 'No Cache']);

    settings('general.site-name');

    expect(Cache::has('settings.global'))->toBeFalse();
});

it('returns the repository when called without arguments', function () {
    expect(settings())->toBeInstanceOf(SettingsRepository::class);
});

it('gets a typed boolean via repository bool()', function () {
    Setting::query()->create(['key' => 'general.launched', 'value' => '1']);

    expect(settings()->bool('general.launched'))->toBeTrue();
});

it('returns bool default when no DB value', function () {
    expect(settings()->bool('general.launched'))->toBeFalse();
    expect(settings()->bool('general.launched', true))->toBeTrue();
});

it('gets a typed integer via repository int()', function () {
    Setting::query()->create(['key' => 'general.some-number', 'value' => '42']);

    expect(settings()->int('general.some-number'))->toBe(42);
});

it('returns int default when no DB value', function () {
    expect(settings()->int('general.some-number'))->toBe(0);
    expect(settings()->int('general.some-number', 10))->toBe(10);
});

it('gets a typed string via repository string()', function () {
    Setting::query()->create(['key' => 'general.site-name', 'value' => 'My Site']);

    expect(settings()->string('general.site-name'))->toBe('My Site');
});

it('returns string default when no DB value', function () {
    expect(settings()->string('general.site-name'))->toBe('');
    expect(settings()->string('general.site-name', 'fallback'))->toBe('fallback');
});

it('gets a typed array via repository array()', function () {
    Setting::query()->create(['key' => 'general.tags', 'value' => '["a","b","c"]']);

    expect(settings()->array('general.tags'))->toBe(['a', 'b', 'c']);
});

it('returns array default when no DB value', function () {
    expect(settings()->array('general.tags'))->toBe([]);
    expect(settings()->array('general.tags', ['x']))->toBe(['x']);
});

it('returns array default when value is not valid json', function () {
    Setting::query()->create(['key' => 'general.tags', 'value' => 'not-json']);

    expect(settings()->array('general.tags', ['fallback']))->toBe(['fallback']);
});

it('persists a value via repository set()', function () {
    settings()->set('general.site-name', 'New Site');

    expect(Setting::query()->where('key', 'general.site-name')->value('value'))
        ->toBe('New Site');
    expect(Cache::has('settings.global'))->toBeFalse();
});

it('removes setting via repository set() with null', function () {
    Setting::query()->create(['key' => 'general.site-name', 'value' => 'Old']);

    settings()->set('general.site-name', null);

    expect(Setting::query()->where('key', 'general.site-name')->exists())->toBeFalse();
});

it('persists boolean via repository set()', function () {
    settings()->set('general.launched', true);

    expect(Setting::query()->where('key', 'general.launched')->value('value'))->toBe('1');

    settings()->set('general.launched', false);

    expect(Setting::query()->where('key', 'general.launched')->value('value'))->toBe('0');
});

it('resolves cast for a known setting key', function () {
    expect(settings()->getCast('general.launched'))->toBe('boolean');
});

it('returns null cast for an unknown setting key', function () {
    expect(settings()->getCast('general.site-name'))->toBeNull();
});

it('caches cast lookups', function () {
    settings()->getCast('general.launched');

    expect(Cache::has('settings.casts'))->toBeTrue();
});

it('does not re-query when the database has zero settings', function () {
    // Prime the in-memory cache with an empty table
    expect(settings()->get('general.site-name'))->toBeNull();

    // Insert a row directly, bypassing model events
    DB::table('settings')->insert(['key' => 'general.site-name', 'value' => 'Sneaky', 'team_id' => null]);

    // The in-memory cache should still serve the empty snapshot
    expect(settings()->get('general.site-name'))->toBeNull();
});

it('returns updated value from get() after set() in the same request', function () {
    settings()->set('general.site-name', 'First');

    expect(settings()->get('general.site-name'))->toBe('First');

    settings()->set('general.site-name', 'Second');

    expect(settings()->get('general.site-name'))->toBe('Second');
});

it('returns updated value from get() after SettingGroup::save()', function () {
    $group = new TestGeneralSettings;
    $group->save(['site_name' => 'Before']);

    expect(settings()->get('general.site-name'))->toBe('Before');

    $group->save(['site_name' => 'After']);

    expect(settings()->get('general.site-name'))->toBe('After');
});

it('serves getCast() from in-memory cache after Laravel cache is cleared', function () {
    $repo = app(SettingsRepository::class);

    // Prime in-memory casts cache
    expect($repo->getCast('general.launched'))->toBe('boolean');

    // Forget only the Laravel cache layer
    Cache::forget($repo->castsCacheKey());

    // In-memory cache still serves the cast
    expect($repo->getCast('general.launched'))->toBe('boolean');
});

it('loads SettingGroup without extra queries when repo cache is primed', function () {
    Setting::query()->create(['key' => 'general.site-name', 'value' => 'Cached Site']);
    Setting::query()->create(['key' => 'general.launched', 'value' => '1']);

    // Prime the repo in-memory cache
    settings()->all();

    // load() should not hit the DB
    DB::enableQueryLog();
    $state = (new TestGeneralSettings)->load();
    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    expect($queries)->toBeEmpty();
    expect($state)->toMatchArray([
        'site_name' => 'Cached Site',
        'launched' => true,
    ]);
});
