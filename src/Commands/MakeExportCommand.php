<?php

namespace Luminix\Sheets\Commands;

use Illuminate\Console\GeneratorCommand;
use Illuminate\Support\Str;

class MakeExportCommand extends GeneratorCommand
{
    protected $signature = 'make:export {name : The class name, e.g. UserExport}';

    protected $description = 'Create a new Luminix Sheets export class';

    protected $type = 'Export';

    protected function getStub(): string
    {
        return __DIR__ . '/../../stubs/export.stub';
    }

    protected function getDefaultNamespace($rootNamespace): string
    {
        return $rootNamespace . '\\Sheets\\Export';
    }

    protected function getPath($name): string
    {
        $name = Str::replaceFirst($this->rootNamespace(), '', $name);

        return app_path(str_replace('\\', '/', $name) . '.php');
    }

    protected function buildClass($name): string
    {
        $stub = parent::buildClass($name);

        $model = Str::of(class_basename($name))
            ->replaceLast('Export', '')
            ->toString();

        return str_replace('{{ model }}', $model, $stub);
    }
}
