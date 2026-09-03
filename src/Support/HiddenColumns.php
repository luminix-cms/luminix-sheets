<?php

namespace Luminix\Sheets\Support;

use Illuminate\Database\Eloquent\Model;
use ReflectionProperty;

/**
 * Resolves which model columns should be excluded from import / export.
 *
 * The model may declare any of the following to customise the behaviour.
 * Properties may be protected; methods must be public.
 *
 *   // Always hidden (import + export). Defaults to the model's $hidden.
 *   protected array $sheetsHidden = ['password', 'remember_token'];
 *
 *   // Hidden only during export
 *   protected array $sheetsHiddenForExport = ['secret'];
 *
 *   // Hidden only during import
 *   protected array $sheetsHiddenForImport = ['computed_column'];
 */
class HiddenColumns
{
    public static function forExport(Model $model): array
    {
        $base = self::resolve($model, 'sheetsHidden', $model->getHidden());
        $extra = self::resolve($model, 'sheetsHiddenForExport', []);

        return array_values(array_unique(array_merge($base, $extra)));
    }

    public static function forImport(Model $model): array
    {
        $base = self::resolve($model, 'sheetsHidden', $model->getHidden());
        $extra = self::resolve($model, 'sheetsHiddenForImport', []);

        // Keys are always managed by the DB
        $extra[] = $model->getKeyName();

        return array_values(array_unique(array_merge($base, $extra)));
    }

    /**
     * Reads the declaration off the model.
     *
     * A protected property is read through reflection on purpose: `$model->$name`
     * from outside the class falls through to Eloquent's `__get`, which resolves
     * it as an attribute, finds nothing and yields null — silently turning a
     * declared hidden column into an exported one.
     */
    protected static function resolve(Model $model, string $name, array $default): array
    {
        if (property_exists($model, $name)) {
            $property = new ReflectionProperty($model, $name);
            $property->setAccessible(true);

            if ($property->isInitialized($model)) {
                return (array) $property->getValue($model);
            }
        }

        if (method_exists($model, $name)) {
            return (array) $model->{$name}();
        }

        return $default;
    }
}
