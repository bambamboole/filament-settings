<?php
declare(strict_types=1);
namespace Bambamboole\FilamentSettings;

use Bambamboole\FilamentSettings\Pages\ManageSettings;
use Closure;
use Filament\Contracts\Plugin;
use Filament\Panel;

class FilamentSettingsPlugin implements Plugin
{
    /** @var array<class-string<SettingGroup>> */
    protected array $groups = [];

    protected ?Closure $canAccess = null;

    protected ?Closure $tenantResolver = null;

    protected ?int $navigationSort = 99;

    protected ?string $navigationGroup = null;

    public function getId(): string
    {
        return 'filament-settings';
    }

    public function register(Panel $panel): void
    {
        $panel->pages([
            ManageSettings::class,
        ]);
    }

    public function boot(Panel $panel): void
    {
        if ($this->tenantResolver !== null) {
            app(SettingsRepository::class)->setTenantResolver($this->tenantResolver);
        }
    }

    /**
     * @param  array<class-string<SettingGroup>>  $groups
     */
    public function groups(array $groups): static
    {
        $this->groups = $groups;

        return $this;
    }

    /**
     * @return array<class-string<SettingGroup>>
     */
    public function getGroups(): array
    {
        return $this->groups;
    }

    public function canAccess(Closure $callback): static
    {
        $this->canAccess = $callback;

        return $this;
    }

    public function isAuthorized(): bool
    {
        if ($this->canAccess === null) {
            return true;
        }

        return ($this->canAccess)();
    }

    public function tenant(Closure $resolver): static
    {
        $this->tenantResolver = $resolver;

        return $this;
    }

    public function navigationSort(int $sort): static
    {
        $this->navigationSort = $sort;

        return $this;
    }

    public function getNavigationSort(): ?int
    {
        return $this->navigationSort;
    }

    public function navigationGroup(?string $group): static
    {
        $this->navigationGroup = $group;

        return $this;
    }

    public function getNavigationGroup(): ?string
    {
        return $this->navigationGroup;
    }

    public static function make(): static
    {
        return app(static::class);
    }

    public static function get(): static
    {
        /** @var static $plugin */
        $plugin = filament(app(static::class)->getId());

        return $plugin;
    }
}
