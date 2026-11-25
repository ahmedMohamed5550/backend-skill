<?php

/*
|--------------------------------------------------------------------------
| Rate Limiting Configuration
|--------------------------------------------------------------------------
|
| Approved limits for the API rate limiters defined in the approved
| AppServiceProvider template (references/api-standards.md, Rate Limiting).
| Every value is overridable per environment.
|
| The `auth` values are enforced by the throttle:auth middleware on the
| login endpoint with two independent buckets: one per normalized email
| (trimmed and lowercased) and one per IP address. Each bucket carries a
| per-minute and a per-hour window; all four limits must pass for the
| request to proceed.
|
| The `password_reset` values are enforced by the throttle:password_reset
| middleware on the forgot-password and reset-password endpoints
| (references/authentication.md, Password Reset) with the same bucket
| pattern as `auth`. On top of them, the native Password broker throttles
| repeated reset links per email through auth.passwords.users.throttle
| (config/auth.php).
|
| The `email_resend` values are enforced by the throttle:email_resend
| middleware on the resend-verification endpoint
| (references/authentication.md, Email Verification); its buckets key per
| authenticated user id.
|
| The /oauth/token endpoint keeps Passport's own built-in `throttle`
| middleware (60 requests per minute, Laravel default) and is not
| configured here.
|
*/

return [

    /*
    |----------------------------------------------------------------------
    | API baseline (throttle:api)
    |----------------------------------------------------------------------
    |
    | Applied to every /api/v1 request through $middleware->throttleApi()
    | in bootstrap/app.php, keyed by the authenticated user id or, for
    | guests, the client IP.
    |
    */

    'api_per_minute' => env('RATE_LIMIT_API_PER_MINUTE', 60),

    /*
    |----------------------------------------------------------------------
    | Authentication endpoints (throttle:auth)
    |----------------------------------------------------------------------
    |
    | Login attempts per normalized email AND per IP address. The limiter
    | counts every attempt (successful or not) because it runs before the
    | Controller.
    |
    */

    'auth_per_minute' => env('RATE_LIMIT_AUTH_PER_MINUTE', 5),
    'auth_per_hour' => env('RATE_LIMIT_AUTH_PER_HOUR', 30),

    /*
    |----------------------------------------------------------------------
    | Password reset endpoints (throttle:password_reset)
    |----------------------------------------------------------------------
    |
    | Forgot-password and reset-password attempts per normalized email AND
    | per IP address (references/authentication.md, Password Reset). The
    | limiter counts every attempt because it runs before the Controller.
    |
    */

    'password_reset_per_minute' => env('RATE_LIMIT_PASSWORD_RESET_PER_MINUTE', 5),
    'password_reset_per_hour' => env('RATE_LIMIT_PASSWORD_RESET_PER_HOUR', 30),

    /*
    |----------------------------------------------------------------------
    | Verification email resend (throttle:email_resend)
    |----------------------------------------------------------------------
    |
    | Resend attempts per authenticated user id
    | (references/authentication.md, Email Verification). The per-minute
    | default matches Laravel's documented verification resend throttle
    | (6 per minute).
    |
    */

    'email_resend_per_minute' => env('RATE_LIMIT_EMAIL_RESEND_PER_MINUTE', 6),
    'email_resend_per_hour' => env('RATE_LIMIT_EMAIL_RESEND_PER_HOUR', 30),

    /*
    |----------------------------------------------------------------------
    | Reserved: future OTP limiters
    |----------------------------------------------------------------------
    |
    | Values prepared for the optional OTP verification feature
    | (references/authentication.md, Phone OTP Verification). No endpoint
    | or middleware consumes them yet — attaching those limiters is part
    | of implementing that approved feature, not before. Until then these
    | keys are inert.
    |
    */

    'otp_send_per_hour' => env('RATE_LIMIT_OTP_SEND_PER_HOUR', 3),
    'otp_send_per_day' => env('RATE_LIMIT_OTP_SEND_PER_DAY', 10),
    'otp_verify_per_code' => env('RATE_LIMIT_OTP_VERIFY_PER_CODE', 5),
    'otp_verify_per_hour' => env('RATE_LIMIT_OTP_VERIFY_PER_HOUR', 10),
];
