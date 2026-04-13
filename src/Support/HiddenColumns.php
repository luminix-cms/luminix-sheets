<?php

namespace Luminix\Sheets\Support;

use Illuminate\Database\Eloquent\Model;

/**
 * Resolves which model columns should be excluded from import / export.
 *
 * The model may define any of the following static properties or methods to
 * customise the behaviour:
 *
 *   // Always hidden (import + export)
 *   protected array $sheetsHidden = ['password', 'remember_token'];
 *
 *   // Hidden only during export
 *   protected array $sheetsHiddenForExport = ['secret'];
 *
 *   // Hidden only during import
 *   protected array $sheetsHiddenForImport = ['computed_column'];
 *
 */
class HiddenColumns
{
    public static function forExport(Model $model): array
    {
        $base = self::resolveProperty($model, 'sheetsHidden', $model->getHidden());
        $extra = self::resolveProperty($model, 'sheetsHiddenForExport', []);

        return array_unique(array_merge($base, $extra));
    }

    public static function forImport(Model $model): array
    {
        $base = self::resolveProperty($model, 'sheetsHidden', $model->getHidden());
        $extra = self::resolveProperty($model, 'sheetsHiddenForImport', []);

        // Keys are always managed by the DB
        $extra[] = $model->getKeyName();

        return array_unique(array_merge($base, $extra));
    }

    protected static function resolveProperty(Model $model, string $property, array $default): array
    {
        if (property_exists($model, $property)) {
            return (array) $model->{$property};
        }

        if (method_exists($model, $property)) {
            return (array) $model->{$property}();
        }

        return $default;
    }
}
