<?php
declare(strict_types=1);
namespace Bambamboole\FilamentSettings\Database\Factories;

use Bambamboole\FilamentSettings\Models\Setting;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Setting> */
final class SettingFactory extends Factory
{
    protected $model = Setting::class;

    public function definition(): array
    {
        return [
            'key' => fake()->unique()->slug(2, false),
            'value' => fake()->word(),
        ];
    }
}
