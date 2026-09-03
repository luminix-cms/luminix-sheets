<?php

namespace Luminix\Sheets\Support;

use InvalidArgumentException;
use Luminix\Sheets\Contracts\ExportsFromSheet;
use Luminix\Sheets\Contracts\ImportsFromSheet;
use Luminix\Sheets\Exportable;
use Luminix\Sheets\Importable;
use ReflectionAttribute;
use ReflectionClass;

class ModelSheetResolver
{
    /**
     * The import handler for the given model class, or null when the model
     * does not carry the #[Importable] attribute.
     */
    public static function importer(string $modelClass): ?ImportsFromSheet
    {
        return self::handler($modelClass, Importable::class, ImportsFromSheet::class);
    }

    /**
     * The export handler for the given model class, or null when the model
     * does not carry the #[Exportable] attribute.
     */
    public static function exporter(string $modelClass): ?ExportsFromSheet
    {
        return self::handler($modelClass, Exportable::class, ExportsFromSheet::class);
    }

    public static function isImportable(string $modelClass): bool
    {
        return self::attribute($modelClass, Importable::class) !== null;
    }

    public static function isExportable(string $modelClass): bool
    {
        return self::attribute($modelClass, Exportable::class) !== null;
    }

    protected static function handler(string $modelClass, string $attributeClass, string $contract): ?object
    {
        $attribute = self::attribute($modelClass, $attributeClass);

        if (! $attribute) {
            return null;
        }

        $handlerClass = $attribute->newInstance()->handler;

        if (! class_exists($handlerClass) || ! is_subclass_of($handlerClass, $contract)) {
            throw new InvalidArgumentException(
                "[{$handlerClass}], declared on [{$modelClass}], must implement [{$contract}]."
            );
        }

        return new $handlerClass($modelClass);
    }

    /**
     * IS_INSTANCEOF so a project may subclass the attribute; the search walks
     * up the hierarchy so a base model can mark a whole family importable.
     */
    protected static function attribute(string $modelClass, string $attributeClass): ?ReflectionAttribute
    {
        if (! class_exists($modelClass)) {
            return null;
        }

        $reflection = new ReflectionClass($modelClass);

        while ($reflection) {
            $attributes = $reflection->getAttributes($attributeClass, ReflectionAttribute::IS_INSTANCEOF);

            if (isset($attributes[0])) {
                return $attributes[0];
            }

            $reflection = $reflection->getParentClass();
        }

        return null;
    }
}
