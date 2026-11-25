<?php

return [

    /*
    |--------------------------------------------------------------------------
    | OAuth Token Lifetimes
    |--------------------------------------------------------------------------
    |
    | Lifetimes for the OAuth tokens Passport issues through the first-party
    | authorization code + PKCE login flow (references/authentication.md,
    | Token Lifecycle). The scaffold-provided AppServiceProvider template
    | reads these values in boot() through Passport::tokensExpireIn() and
    | Passport::refreshTokensExpireIn().
    |
    | Access tokens are short-lived Bearer JWTs; the refresh token is the
    | long-lived credential that rotates on every refresh. Values are
    | integers: minutes for the access token, days for the refresh token.
    |
    */

    'access_token_expires_in' => env('OAUTH_ACCESS_TOKEN_EXPIRES_IN', 15),

    'refresh_token_expires_in' => env('OAUTH_REFRESH_TOKEN_EXPIRES_IN', 30),

];
