<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Routes
    |--------------------------------------------------------------------------
    |
    | The import/export routes are injected into the route set luminix/backend
    | already generates for each model, so they inherit its prefix, middleware
    | and naming. Disable this to register them yourself.
    |
    */

    'routes' => [
        'enabled' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Permissions
    |--------------------------------------------------------------------------
    |
    | Maps each sheet action to the permission verb checked against the model
    | alias, e.g. 'read' becomes the 'read-player' gate and the `allowed('read')`
    | query scope. luminix/backend's own permission map has no entry for these
    | two actions, so the package resolves them here.
    |
    | Setting an action to null disables BOTH the gate and the row scope for it.
    |
    */

    'permissions' => [
        'export' => 'read',
        'import' => 'create',
    ],

    /*
    |--------------------------------------------------------------------------
    | Import settings
    |--------------------------------------------------------------------------
    */

    'import' => [
        // Maximum allowed upload size in kilobytes (default: 10 MB)
        'max_file_size_kb' => env('LUMINIX_SHEETS_MAX_UPLOAD_KB', 10240),

        // Accepted upload formats. Defaults to xlsx alone, matching the
        // convention in the consuming applications. OpenSpout also reads csv
        // and ods; add them here when a project needs them.
        'formats' => ['xlsx'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Export settings
    |--------------------------------------------------------------------------
    */

    'export' => [
        // Default format when the handler does not override format(): xlsx | csv | ods
        'default_format' => env('LUMINIX_SHEETS_EXPORT_FORMAT', 'xlsx'),

        // Rows fetched per database round-trip while streaming an export.
        'chunk_size' => env('LUMINIX_SHEETS_EXPORT_CHUNK', 1000),

        // Maximum number of rows written in a single export request.
        // Set to null to disable the limit.
        'max_rows' => env('LUMINIX_SHEETS_EXPORT_MAX_ROWS'),
    ],

];
