<?php

/*
|--------------------------------------------------------------------------
| Module Route Template
|--------------------------------------------------------------------------
|
| Approved structure for every module route file.
| Create the file inside routes/v1/ (never directly inside routes/), for
| example routes/v1/{{module}}.php, and require it from routes/api1.php:
|
| require __DIR__ . '/v1/{{module}}.php';
|
*/

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\V1\{{Module}}Controller;

Route::controller({{Module}}Controller::class)
    ->prefix('{{module}}')
    ->group(function () {
        // Static action paths must be defined BEFORE /{uuid} routes,
        // otherwise the parameter swallows them (e.g. GET /bulk-delete
        // would match show('{uuid}') instead of bulkDelete()).
        Route::get('/', 'index');
        Route::post('/', 'store');
        Route::delete('/bulk-delete', 'bulkDelete');
        Route::get('/{uuid}', 'show');
        Route::put('/{uuid}', 'update');
        Route::delete('/{uuid}', 'destroy');

        // Add additional module routes here.
        // Use the same prefix and define only the action path.
        // Always register new static action paths above the /{uuid} routes.
    });
