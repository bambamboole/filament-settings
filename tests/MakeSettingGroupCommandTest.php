<?php
declare(strict_types=1);

afterEach(function () {
    $dir = app_path('Settings');

    if (is_dir($dir)) {
        array_map('unlink', glob($dir.'/*.php') ?: []);
        rmdir($dir);
    }
});

it('generates a SettingGroup class', function () {
    $this->artisan('settings:make-group')
        ->expectsQuestion('Class name', 'GeneralSettings')
        ->expectsQuestion('Group key', 'general')
        ->expectsQuestion('Label', 'General')
        ->assertSuccessful();

    $path = app_path('Settings/GeneralSettings.php');
    expect(file_exists($path))->toBeTrue();

    $content = file_get_contents($path);
    expect($content)
        ->toContain('declare(strict_types=1)')
        ->toContain('namespace App\Settings')
        ->toContain('use Bambamboole\FilamentSettings\SettingGroup')
        ->toContain('class GeneralSettings extends SettingGroup')
        ->toContain("return 'general';")
        ->toContain("return 'General';")
        ->toContain('public function schema(): array');
});

it('derives the group key and label from the class name', function () {
    $this->artisan('settings:make-group')
        ->expectsQuestion('Class name', 'MailNotificationSettings')
        ->expectsQuestion('Group key', 'mail-notification')
        ->expectsQuestion('Label', 'Mail Notification')
        ->assertSuccessful();

    $content = file_get_contents(app_path('Settings/MailNotificationSettings.php'));
    expect($content)
        ->toContain("return 'mail-notification';")
        ->toContain("return 'Mail Notification';");
});

it('fails when the file already exists', function () {
    mkdir(app_path('Settings'), 0755, true);
    file_put_contents(app_path('Settings/GeneralSettings.php'), '<?php // existing');

    $this->artisan('settings:make-group')
        ->expectsQuestion('Class name', 'GeneralSettings')
        ->expectsQuestion('Group key', 'general')
        ->expectsQuestion('Label', 'General')
        ->assertFailed();

    expect(file_get_contents(app_path('Settings/GeneralSettings.php')))->toBe('<?php // existing');
});
