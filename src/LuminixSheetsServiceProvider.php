<?php

namespace Luminix\Sheets;

use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Luminix\Backend\Controllers\ResourceController;
use Luminix\Backend\Facades\Finder;
use Luminix\Sheets\Exceptions\ImportValidationException;
use Luminix\Sheets\Http\Requests\ImportRequest;
use Luminix\Sheets\Support\ModelSheetResolver;
use Luminix\Sheets\Support\SheetEngine;

class LuminixSheetsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/sheets.php', 'luminix.sheets');
    }

    public function boot(Router $router): void
    {
        $this->publishConfig();
        $this->publishStubs();
        $this->registerCommands();
        $this->registerRoutes($router);
        $this->registerMacros();
        $this->extendManifest();
    }

    /*
    * Macros on ResourceController
    */
    protected function registerMacros(): void
    {
        /**
         * POST /luminix-api/{model}/import
         * Accepts a multipart/form-data upload with a `file` field.
         */
        ResourceController::macro('import', function () {
            $request = app(ImportRequest::class);

            /** @var ResourceController $this */
            [
                'class'      => $class,
                'alias'      => $alias,
                'permission' => $permission,
            ] = $this->inferRequestParameters();

            // Permission gate (same pattern as store)
            if (
                $permission
                && config('luminix.backend.security.gates_enabled', true)
                && !Gate::allows($permission . '-' . $alias, [null])
            ) {
                abort(401);
            }

            if (!ModelSheetResolver::isImportable($class)) {
                abort(404, "Model [{$class}] does not support import.");
            }

            $handler = ModelSheetResolver::importer($class);

            try {
                $imported = SheetEngine::import($class, $handler, $request->file('file'));
            } catch (ImportValidationException $e) {
                return response()->json($e->toArray(), 422);
            }

            return response()->json([
                'message' => $imported->count() . ' record(s) imported successfully.',
                'count'   => $imported->count(),
            ], 201);
        });

        /**
         * GET /luminix-api/{model}/export
         * Returns a streamed file download.
         */
        ResourceController::macro('export', function () {
            $request = app(Request::class);

            /** @var ResourceController $this */
            [
                'class'      => $class,
                'alias'      => $alias,
                'permission' => $permission,
            ] = $this->inferRequestParameters();

            if (
                $permission
                && config('luminix.backend.security.gates_enabled', true)
                && !Gate::allows($permission . '-' . $alias, [null])
            ) {
                abort(401);
            }

            if (!ModelSheetResolver::isExportable($class)) {
                abort(404, "Model [{$class}] does not support export.");
            }

            $handler = ModelSheetResolver::exporter($class);

            // Build the base query the same way luminixQuery would, but without
            // pagination — we want all allowed records.
            $query = $class::beforeLuminix($request)
                ->where(function ($q) use ($permission) {
                    if ($permission) {
                        $q->allowed($permission);
                    }
                })
                ->afterLuminix($request);

            return SheetEngine::export($class, $handler, $query);
        });
    }

    protected function registerRoutes(Router $router): void
    {
        if (!config('luminix.sheets.routes.enabled', true)) {
            return;
        }

        $prefix = config('luminix.sheets.routes.prefix', 'luminix-api');
        $middleware = config('luminix.sheets.routes.middleware', ['luminix-api', 'auth:sanctum']);

        $router->group([
            'prefix' => $prefix,
            'middleware' => $middleware,
        ], function (Router $router) {
            $models = Finder::all();

            foreach ($models as $alias => $class) {
                if (ModelSheetResolver::isImportable($class)) {
                    $router->post(
                        "{$alias}/import",
                        [ResourceController::class, 'import']
                    )->name("luminix.{$alias}.import");
                }

                if (ModelSheetResolver::isExportable($class)) {
                    $router->get(
                        "{$alias}/export",
                        [ResourceController::class, 'export']
                    )->name("luminix.{$alias}.export");
                }
            }
        });
    }


    /**
     * Hooks into the Luminix model manifest so the frontend knows which models
     * support import / export.
     *
     * Each model entry in the manifest will get two boolean flags:
     *   "importable": true | false
     *   "exportable": true | false
     */
    protected function extendManifest(): void
    {
        // Luminix fires a `luminix:manifest` event (or uses a macro/hook) to let
        // packages extend each model's manifest entry. The exact API depends on
        // the luminix/backend version; the hook below follows the documented
        // `Finder::extend()` pattern.
        if (!method_exists(Finder::getFacadeRoot(), 'extend')) {
            return;
        }

        Finder::extend(function (string $alias, string $class, array $entry) {
            $entry['importable'] = ModelSheetResolver::isImportable($class);
            $entry['exportable'] = ModelSheetResolver::isExportable($class);

            return $entry;
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
            __DIR__ . '/../config/sheets.php' => config_path('luminix/sheets.php'),
        ], 'luminix-sheets-config');
    }

    protected function publishStubs(): void
    {
        $this->publishes([
            __DIR__ . '/../stubs' => base_path('stubs/luminix-sheets'),
        ], 'luminix-sheets-stubs');
    }
}
