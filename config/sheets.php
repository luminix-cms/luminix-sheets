<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Routes
    |--------------------------------------------------------------------------
    |
    | Controls whether the package registers its own import/export routes
    | automatically. Disable this if you prefer to register routes manually.
    |
    */

    'routes' => [
        'enabled' => true,
        'prefix' => 'luminix-api',
        'middleware' => ['api', 'auth:sanctum'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Import settings
    |--------------------------------------------------------------------------
    */

    'import' => [
        // Maximum allowed upload size in kilobytes (default: 10 MB)
        'max_file_size_kb' => env('LUMINIX_SHEETS_MAX_UPLOAD_KB', 10240),
    ],

    /*
    |--------------------------------------------------------------------------
    | Export settings
    |--------------------------------------------------------------------------
    */

    'export' => [
        // Default format when the handler does not specify one: xlsx | csv | ods
        'default_format' => env('LUMINIX_SHEETS_EXPORT_FORMAT', 'xlsx'),

        // Maximum number of rows streamed in a single export request.
        // Set to null to disable the limit.
        'max_rows' => env('LUMINIX_SHEETS_EXPORT_MAX_ROWS', null),
    ],

];
