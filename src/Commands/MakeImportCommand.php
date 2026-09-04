<?php

namespace Luminix\Sheets\Commands;

use Illuminate\Console\GeneratorCommand;
use Illuminate\Support\Str;

class MakeImportCommand extends GeneratorCommand
{
    protected $signature = 'make:import {name : The class name, e.g. UserImport}';

    protected $description = 'Create a new Luminix Sheets import class';

    protected $type = 'Import';

    protected function getStub(): string
    {
        return __DIR__.'/../../stubs/import.stub';
    }

    protected function getDefaultNamespace($rootNamespace): string
    {
        return $rootNamespace.'\\Sheets\\Import';
    }

    protected function buildClass($name): string
    {
        $stub = parent::buildClass($name);

        // Inject a sensible model name guess from the class name
        // e.g. "UserImport" → "User"
        $model = Str::of(class_basename($name))
            ->replaceLast('Import', '')
            ->toString();

        return str_replace('{{ model }}', $model, $stub);
    }
}
