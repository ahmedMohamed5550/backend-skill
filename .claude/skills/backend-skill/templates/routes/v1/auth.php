<?php

use App\Http\Controllers\V1\AuthController;
use Illuminate\Support\Facades\Route;

Route::controller(AuthController::class)
    ->prefix('auth')
    ->group(function () {
        // Public endpoints. throttle:auth (references/api-standards.md,
        // Rate Limiting) limits login and registration attempts with
        // independent buckets per normalized email and per IP. The password
        // reset endpoints run under throttle:password_reset (same bucket
        // pattern, limits from the reserved config keys — references/
        // authentication.md, Password Reset).
        Route::post('/register', 'register')->middleware('throttle:auth');
        Route::post('/login', 'login')->middleware('throttle:auth');
        Route::post('/forgot-password', 'forgotPassword')->middleware('throttle:password_reset');
        Route::post('/reset-password', 'resetPassword')->middleware('throttle:password_reset');

        // Laravel-native email verification handler (references/
        // authentication.md, Email Verification). The route name is the one
        // the framework's VerifyEmail notification builds its signed URL
        // for; the VerifyEmail::createUrlUsing() registration in the
        // AppServiceProvider builds that URL with the user's uuid (public
        // API identifiers are UUIDs, never database ids). The signature IS
        // the authorization — exactly like Laravel's own verification flow,
        // an email client cannot present a Bearer token — so no auth:api
        // middleware here; the ValidateSignature middleware rejects expired
        // or tampered links with 403 before the controller runs.
        Route::get('/email/verify/{uuid}/{hash}', 'verifyEmail')
            ->middleware('signed')
            ->name('verification.verify');

        Route::middleware('auth:api')->group(function () {
            Route::post('/logout', 'logout');
            Route::get('/me', 'me');

            // Resend the verification email for the authenticated user.
            // throttle:email_resend keys per authenticated user id
            // (references/api-standards.md, Rate Limiting).
            Route::post('/email/resend', 'resendVerificationEmail')->middleware('throttle:email_resend');
        });
    });
