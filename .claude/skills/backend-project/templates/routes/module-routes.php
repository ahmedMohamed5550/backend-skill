<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\V1\{{Module}}\{{Module}}Controller;

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
