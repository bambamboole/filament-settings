<?php
declare(strict_types=1);

use Bambamboole\FilamentSettings\SettingsRepository;
use Bambamboole\FilamentSettings\Tests\TestCase;

uses(TestCase::class)->in(__DIR__);

function setTenant(?int $id): void
{
    app(SettingsRepository::class)->setTenantResolver(fn () => $id);
}
