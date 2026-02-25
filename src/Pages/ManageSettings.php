<?php
declare(strict_types=1);
namespace Bambamboole\FilamentSettings\Pages;

use BackedEnum;
use Bambamboole\FilamentSettings\FilamentSettingsPlugin;
use Bambamboole\FilamentSettings\SettingGroup;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class ManageSettings extends Page
{
    protected static BackedEnum|string|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected string $view = 'filament-settings::pages.manage-settings';

    /** @var array<string, array<string, mixed>> */
    public array $formState = [];

    #[\Override]
    public static function canAccess(): bool
    {
        return FilamentSettingsPlugin::get()->isAuthorized();
    }

    #[\Override]
    public static function getNavigationSort(): ?int
    {
        return FilamentSettingsPlugin::get()->getNavigationSort();
    }

    #[\Override]
    public static function getNavigationGroup(): ?string
    {
        return FilamentSettingsPlugin::get()->getNavigationGroup();
    }

    public function mount(): void
    {
        foreach ($this->resolveGroups() as $group) {
            $this->formState[$group::key()] = $group->load();
        }
    }

    #[\Override]
    public function content(Schema $schema): Schema
    {
        $tabs = [];

        foreach ($this->resolveGroups() as $group) {
            $key = $group::key();

            $tabs[] = Tab::make($group->label())
                ->key($key)
                ->icon($group->icon())
                ->schema([
                    Group::make($group->schema())->statePath($key),
                    Actions::make([
                        Action::make("save_{$key}")
                            ->label(__('filament-settings::filament-settings.action.save'))
                            ->icon(Heroicon::OutlinedCheck)
                            ->action(fn () => $this->saveGroup($key)),
                    ]),
                ]);
        }

        return $schema
            ->statePath('formState')
            ->components([
                Tabs::make('settings')
                    ->persistTabInQueryString()
                    ->vertical()
                    ->id('tabs')
                    ->tabs($tabs),
            ]);
    }

    public function saveGroup(string $groupKey): void
    {
        foreach ($this->resolveGroups() as $group) {
            if ($group::key() !== $groupKey) {
                continue;
            }

            $group->save($this->formState[$groupKey] ?? []);

            Notification::make()
                ->success()
                ->title(__('filament-settings::filament-settings.notification.saved'))
                ->send();

            return;
        }
    }

    #[\Override]
    public function getTitle(): string
    {
        return __('filament-settings::filament-settings.title');
    }

    #[\Override]
    public function getHeading(): string
    {
        return __('filament-settings::filament-settings.heading');
    }

    /**
     * @return array<SettingGroup>
     */
    private function resolveGroups(): array
    {
        $groupClasses = FilamentSettingsPlugin::get()->getGroups();

        if ($groupClasses === []) {
            $groupClasses = config('filament-settings.groups', []);
        }

        $groups = array_map(
            static fn (string $class): SettingGroup => new $class,
            array_filter($groupClasses, static fn (string $class): bool => $class::canAccess()),
        );

        usort($groups, fn (SettingGroup $a, SettingGroup $b): int => $a->sort() <=> $b->sort());

        return $groups;
    }
}
