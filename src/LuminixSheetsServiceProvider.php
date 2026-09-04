<?php

namespace Luminix\Sheets;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Luminix\Backend\Controllers\ResourceController;
use Luminix\Backend\Services\RouteGenerator;
use Luminix\Sheets\Exceptions\ImportRowLimitException;
use Luminix\Sheets\Exceptions\ImportValidationException;
use Luminix\Sheets\Exceptions\UnreadableSheetException;
use Luminix\Sheets\Http\Requests\ImportRequest;
use Luminix\Sheets\Support\ModelSheetResolver;
use Luminix\Sheets\Support\SheetEngine;

class LuminixSheetsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Recursive, not mergeConfigFrom: that one is a shallow array_merge, so
        // an application publishing a trimmed config/luminix/sheets.php would
        // drop whole blocks of defaults. `permissions` is why it matters — an
        // absent verb reads as null, and null is documented as turning off both
        // the gate and the row scope.
        $this->replaceConfigRecursivelyFrom(__DIR__.'/../config/sheets.php', 'luminix.sheets');

        // Registered here, not in boot(): luminix/backend generates its routes
        // during its own boot(), and every provider's register() runs first.
        $this->registerRoutes();
    }

    public function boot(): void
    {
        // JSON, not a namespaced group: luminix/admin ships `trans('*')` in the
        // boot payload, where @luminix/sheets-for-mui-cms reads its labels.
        $this->loadJsonTranslationsFrom(__DIR__.'/../lang');

        $this->publishConfig();
        $this->publishStubs();
        $this->registerCommands();
        $this->registerMacros();
        $this->extendManifest();
        $this->extendBootPayload();
    }

    /**
     * The permission verb for a sheet action, or null when the action is open.
     *
     * luminix/backend's own map (`luminix.backend.security.permissions`) has no
     * import/export entry, so `inferRequestParameters()` reports no permission
     * for these routes — which would skip both the gate and the row scope.
     */
    public static function permissionFor(string $action): ?string
    {
        return config("luminix.sheets.permissions.{$action}");
    }

    /*
    * Macros on ResourceController
    */
    protected function registerMacros(): void
    {
        /**
         * POST {prefix}/{models}/import
         * Accepts a multipart/form-data upload with a `file` field.
         */
        ResourceController::macro('import', function () {
            /** @var ResourceController $this */
            [
                'class' => $class,
                'alias' => $alias,
            ] = $this->inferRequestParameters();

            $permission = LuminixSheetsServiceProvider::permissionFor('import');

            if (! ModelSheetResolver::isImportable($class)) {
                abort(404, __('Model [:model] does not support import.', ['model' => $class]));
            }

            if (
                $permission
                && config('luminix.backend.security.gates_enabled', true)
                && ! Gate::allows($permission.'-'.$alias, [null])
            ) {
                abort(401, __('You are not authorized to perform this action.'));
            }

            // Resolved after the gate: validating first would answer an
            // unauthorised caller with a 422 describing the upload rules.
            $request = app(ImportRequest::class);

            $handler = ModelSheetResolver::importer($class);

            try {
                $imported = SheetEngine::import($class, $handler, $request->file('file'));
            } catch (ImportValidationException|ImportRowLimitException|UnreadableSheetException $e) {
                return response()->json($e->toArray(), 422);
            }

            return response()->json([
                'message' => trans_choice(
                    '{0} No records imported.|{1} :count record imported successfully.'
                        .'|[2,*] :count records imported successfully.',
                    $imported,
                    ['count' => $imported],
                ),
                'count' => $imported,
            ], 201);
        });

        /**
         * GET {prefix}/{models}/export
         * Returns a streamed file download.
         */
        ResourceController::macro('export', function () {
            $request = app(Request::class);

            /** @var ResourceController $this */
            [
                'class' => $class,
                'alias' => $alias,
            ] = $this->inferRequestParameters();

            $permission = LuminixSheetsServiceProvider::permissionFor('export');

            if (! ModelSheetResolver::isExportable($class)) {
                abort(404, __('Model [:model] does not support export.', ['model' => $class]));
            }

            if (
                $permission
                && config('luminix.backend.security.gates_enabled', true)
                && ! Gate::allows($permission.'-'.$alias, [null])
            ) {
                abort(401, __('You are not authorized to perform this action.'));
            }

            $handler = ModelSheetResolver::exporter($class);

            // luminixQuery, not a hand-rolled subset: the export must carry the
            // same q / where / tab / order_by the listing was filtered by.
            $query = $class::luminixQuery($request, $permission);

            return SheetEngine::export($class, $handler, $query);
        });
    }

    /**
     * Injects the two routes into the set luminix/backend already generates per
     * model, so they inherit its prefix, middleware and `luminix.{alias}.{action}`
     * naming — and land *before* `show` (`{models}/{id}`) and `update`
     * (POST `{models}/{id}`), which would otherwise swallow them.
     */
    protected function registerRoutes(): void
    {
        RouteGenerator::reducer('modelRoutes', function (array $routes, string $prefix) {
            // Read inside the reducer, not around it: the reducer has to be in
            // place before luminix/backend boots, which is earlier than the
            // point where an application's own config is guaranteed to be
            // merged. Routes are generated later, when the answer is settled.
            if (! config('luminix.sheets.routes.enabled', true)) {
                return $routes;
            }

            // The reducer fires for every model luminix/backend routes, so the
            // attributes have to be checked here. Registering the pair on a
            // model that carries neither would advertise in `route:list`, in
            // the manifest and in generated UI an action that only ever 404s.
            $class = ModelSheetResolver::classForRoutePrefix($prefix);

            if ($class === null) {
                return $routes;
            }

            $sheets = [];

            if (ModelSheetResolver::isExportable($class)) {
                $sheets['export'] = [
                    'path' => $prefix.'/export',
                    'method' => 'get',
                ];
            }

            if (ModelSheetResolver::isImportable($class)) {
                $sheets['import'] = [
                    'path' => $prefix.'/import',
                    'method' => 'post',
                ];
            }

            return [...$sheets, ...$routes];
        });
    }

    /**
     * Adds `importable` / `exportable` to each model's manifest entry so the
     * frontend knows which models offer the actions.
     *
     * The manifest belongs to luminix/frontend, which is an optional dependency.
     */
    protected function extendManifest(): void
    {
        $service = '\Luminix\Frontend\Services\ManifestService';

        if (! class_exists($service)) {
            return;
        }

        $service::reducer('modelManifest', function (array $data, string $class): array {
            return [
                ...$data,
                'importable' => ModelSheetResolver::isImportable($class),
                'exportable' => ModelSheetResolver::isExportable($class),
            ];
        });
    }

    /**
     * Publishes the accepted import formats in the boot payload, which is where
     * the npm package `sheets-for-mui-cms` reads them to build the upload field.
     *
     * `wireConfig` is the only channel: the payload carries `app`, `auth` and the
     * manifest, so nothing else in `config/sheets.php` reaches the browser.
     *
     * The boot payload belongs to luminix/frontend, which is an optional dependency.
     */
    protected function extendBootPayload(): void
    {
        $service = '\Luminix\Frontend\Services\BootService';

        if (! class_exists($service)) {
            return;
        }

        $service::reducer('wireConfig', function (array $boot): array {
            $luminix = $boot['luminix'] ?? [];

            return [
                ...$boot,
                'luminix' => [
                    ...$luminix,
                    'sheets' => [
                        ...($luminix['sheets'] ?? []),
                        'import' => [
                            'formats' => array_values((array) config('luminix.sheets.import.formats', ['xlsx'])),
                        ],
                    ],
                ],
            ];
        });
    }

    protected function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                Commands\MakeImportCommand::class,
                Commands\MakeExportCommand::class,
            ]);
        }
    }

    protected function publishConfig(): void
    {
        $this->publishes([
            __DIR__.'/../config/sheets.php' => config_path('luminix/sheets.php'),
        ], 'luminix-sheets-config');
    }

    protected function publishStubs(): void
    {
        $this->publishes([
            __DIR__.'/../stubs' => base_path('stubs/luminix-sheets'),
        ], 'luminix-sheets-stubs');
    }
}
