<?php
declare(strict_types=1);
namespace Bambamboole\FilamentSettings\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Nette\PhpGenerator\PhpFile;
use Nette\PhpGenerator\PsrPrinter;

use function Laravel\Prompts\intro;
use function Laravel\Prompts\outro;
use function Laravel\Prompts\text;

class MakeSettingGroupCommand extends Command
{
    protected $signature = 'settings:make-group';

    protected $description = 'Generate a new SettingGroup class';

    public function handle(): int
    {
        intro('Create a new SettingGroup');

        $className = text(
            label: 'Class name',
            placeholder: 'GeneralSettings',
            required: true,
            validate: fn (string $value): ?string => preg_match('/^[A-Z][a-zA-Z0-9]*$/', $value)
                ? null
                : 'Must be a valid PascalCase class name.',
        );

        $stripped = Str::beforeLast($className, 'Settings');
        $defaultKey = Str::kebab($stripped !== '' ? $stripped : $className);

        $key = text(
            label: 'Group key',
            default: $defaultKey,
            required: true,
        );

        $label = text(
            label: 'Label',
            default: Str::title(str_replace('-', ' ', $key)),
            required: true,
        );

        $path = app_path('Settings/'.$className.'.php');

        if (file_exists($path)) {
            $this->components->error("app/Settings/{$className}.php already exists.");

            return self::FAILURE;
        }

        if (!is_dir(dirname($path)) && (!mkdir($concurrentDirectory = dirname($path), 0755, true) && !is_dir($concurrentDirectory))) {
            throw new \RuntimeException(sprintf('Directory "%s" was not created', $concurrentDirectory));
        }

        file_put_contents($path, $this->buildClassContent($className, $key, $label));

        outro("Created: app/Settings/{$className}.php");

        return self::SUCCESS;
    }

    private function buildClassContent(string $className, string $key, string $label): string
    {
        $file = new PhpFile;
        $file->setStrictTypes();

        $namespace = $file->addNamespace('App\Settings');
        $namespace->addUse(\Bambamboole\FilamentSettings\SettingGroup::class);

        $class = $namespace->addClass($className);
        $class->setExtends(\Bambamboole\FilamentSettings\SettingGroup::class);

        $class->addMethod('key')
            ->setStatic()
            ->setReturnType('string')
            ->setBody("return '{$key}';");

        $class->addMethod('label')
            ->setReturnType('string')
            ->setBody("return '{$label}';");

        $class->addMethod('schema')
            ->setReturnType('array')
            ->addComment('@return array<\Filament\Schemas\Components\Component>')
            ->setBody('return [];');

        return (new PsrPrinter)->printFile($file);
    }
}
