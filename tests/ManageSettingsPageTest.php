<?php
declare(strict_types=1);

use Bambamboole\FilamentSettings\Models\Setting;
use Bambamboole\FilamentSettings\Pages\ManageSettings;
use Bambamboole\FilamentSettings\Tests\Fixtures\TestUser;
use Illuminate\Support\Facades\Cache;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->user = TestUser::create([
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => bcrypt('password'),
    ]);

    $this->actingAs($this->user);
});

it('can render the page', function () {
    livewire(ManageSettings::class)->assertOk();
});

it('loads empty state when no DB values exist', function () {
    livewire(ManageSettings::class)
        ->assertOk()
        ->assertSet('formState.general.site_name', null)
        ->assertSet('formState.general.launched', null);
});

it('loads existing DB values', function () {
    Setting::query()->create(['key' => 'general.site-name', 'value' => 'My Site']);

    livewire(ManageSettings::class)
        ->assertOk()
        ->assertSet('formState.general.site_name', 'My Site');
});

it('loads toggle values as booleans from database', function () {
    Setting::query()->create(['key' => 'general.launched', 'value' => '0']);

    livewire(ManageSettings::class)
        ->assertOk()
        ->assertSet('formState.general.launched', false);
});

it('saves settings to the database', function () {
    livewire(ManageSettings::class)
        ->set('formState.general.site_name', 'Saved Site')
        ->call('saveGroup', 'general')
        ->assertNotified();

    expect(Setting::query()->where('key', 'general.site-name')->value('value'))
        ->toBe('Saved Site');
});

it('clears cache after saving', function () {
    Cache::forever('settings.global', ['general.site-name' => 'old-cached-value']);

    livewire(ManageSettings::class)
        ->set('formState.general.site_name', 'New Site')
        ->call('saveGroup', 'general')
        ->assertNotified();

    expect(Cache::get('settings.global'))->toBeNull();
});

it('preserves toggle off state through save and reload', function () {
    livewire(ManageSettings::class)
        ->set('formState.general.launched', false)
        ->call('saveGroup', 'general')
        ->assertNotified();

    expect(Setting::query()->where('key', 'general.launched')->value('value'))->toBe('0');

    livewire(ManageSettings::class)
        ->assertSet('formState.general.launched', false);
});

it('removes setting from DB when value is empty', function () {
    Setting::query()->create(['key' => 'general.site-name', 'value' => 'Old']);

    livewire(ManageSettings::class)
        ->set('formState.general.site_name', '')
        ->call('saveGroup', 'general')
        ->assertNotified();

    expect(Setting::query()->where('key', 'general.site-name')->exists())->toBeFalse();
});
