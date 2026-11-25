<?php

/*
|--------------------------------------------------------------------------
| API Version 1 Routes
|--------------------------------------------------------------------------
|
| api1.php is the only skill-generated route file directly inside routes/.
| Every module route file, including Auth, User, and Documentation, must be
| generated inside routes/v1/ and required below.
|
| Example:
| require __DIR__ . '/v1/users.php';
|
*/

// Public API documentation endpoint — included in every project by default.
require __DIR__ . '/v1/documentation.php';

// Auth module (copied by the scaffold): register, login, forgot-password,
// reset-password, and the signed email/verify/{uuid}/{hash} route are
// public; logout, me, and email/resend sit inside the auth:api group. The
// Passport /oauth/token endpoint (code exchange and refresh) is registered
// by the application in AppServiceProvider::boot() — Passport::
// ignoreRoutes() disables Passport's other OAuth routes (references/
// authentication.md, Passport route hardening) — outside the api/v1 prefix.
require __DIR__ . '/v1/auth.php';
