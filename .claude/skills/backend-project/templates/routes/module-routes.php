<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\V1\{{Module}}\{{Module}}Controller;

Route::controller({{Module}}Controller::class)
    ->prefix('{{module}}')
    ->group(function () {
        Route::get('/', 'index');
        Route::get('/{uuid}', 'show');
        Route::post('/', 'store');
        Route::put('/{uuid}', 'update');
        Route::delete('/{uuid}', 'destroy');
        Route::delete('/bulk-delete', 'bulkDelete');

        // Add additional module routes here.
        // Use the same prefix and define only the action path.
    });
