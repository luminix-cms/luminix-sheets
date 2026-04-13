<?php

namespace Luminix\Sheets\Support;

use Luminix\Sheets\Exportable;
use Luminix\Sheets\Importable;
use Luminix\Sheets\Contracts\ExportsFromSheet;
use Luminix\Sheets\Contracts\ImportsFromSheet;

class ModelSheetResolver
{
    /**
     * Returns the ImportHandler instance for the given model class, or null
     * if the model does not have the #[Importable] attribute.
     */
    public static function importer(string $modelClass): ?ImportsFromSheet
    {
        $attribute = self::getAttribute($modelClass, Importable::class);

        if (!$attribute) {
            return null;
        }

        /** @var Importable $instance */
        $instance = $attribute->newInstance();

        return new $instance->handler($modelClass);
    }

    /**
     * Returns the ExportHandler instance for the given model class, or null
     * if the model does not have the #[Exportable] attribute.
     */
    public static function exporter(string $modelClass): ?ExportsFromSheet
    {
        $attribute = self::getAttribute($modelClass, Exportable::class);

        if (!$attribute) {
            return null;
        }

        /** @var Exportable $instance */
        $instance = $attribute->newInstance();

        return new $instance->handler($modelClass);
    }

    public static function isImportable(string $modelClass): bool
    {
        return self::getAttribute($modelClass, Importable::class) !== null;
    }

    public static function isExportable(string $modelClass): bool
    {
        return self::getAttribute($modelClass, Exportable::class) !== null;
    }

    protected static function getAttribute(string $modelClass, string $attributeClass): ?\ReflectionAttribute
    {
        if (!class_exists($modelClass)) {
            return null;
        }

        $reflection = new \ReflectionClass($modelClass);
        $attributes = $reflection->getAttributes($attributeClass);

        return $attributes[0] ?? null;
    }
}
