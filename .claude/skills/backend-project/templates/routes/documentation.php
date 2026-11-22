<?php

use App\Http\Controllers\V1\Documentation\DocumentationController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public Documentation Routes
|--------------------------------------------------------------------------
|
| Serves docs/API_DOCUMENTATION.md so frontend and mobile clients can
| access the API documentation without authentication. This route file
| is intentionally public: no auth and no permission middleware.
|
*/

Route::controller(DocumentationController::class)
    ->prefix('documentation')
    ->group(function () {
        Route::get('/', 'show');
    });
