<?php
declare(strict_types=1);

use Bambamboole\FilamentSettings\Models\Setting;
use Bambamboole\FilamentSettings\SettingsRepository;
use Illuminate\Support\Facades\Cache;

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

    expect(Cache::has('settings.general.site-name'))->toBeTrue();
});

it('clears cache when a setting is saved', function () {
    Setting::query()->create(['key' => 'general.site-name', 'value' => 'Old']);
    settings('general.site-name');

    expect(Cache::has('settings.general.site-name'))->toBeTrue();

    Setting::query()->where('key', 'general.site-name')->first()->update(['value' => 'New']);

    expect(Cache::has('settings.general.site-name'))->toBeFalse();
});

it('clears cache when a setting is deleted', function () {
    $setting = Setting::query()->create(['key' => 'general.site-name', 'value' => 'Old']);
    settings('general.site-name');

    expect(Cache::has('settings.general.site-name'))->toBeTrue();

    $setting->delete();

    expect(Cache::has('settings.general.site-name'))->toBeFalse();
});

it('respects cache.enabled config', function () {
    config()->set('filament-settings.cache.enabled', false);

    Setting::query()->create(['key' => 'general.site-name', 'value' => 'No Cache']);

    settings('general.site-name');

    expect(Cache::has('settings.general.site-name'))->toBeFalse();
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
    expect(Cache::has('settings.general.site-name'))->toBeFalse();
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

    expect(Cache::has('settings.cast.general.launched'))->toBeTrue();
});
