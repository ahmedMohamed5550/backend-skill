<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Social Login Configuration (optional feature)
    |--------------------------------------------------------------------------
    |
    | This file is part of the optional Social Login capability (references/
    | authentication.md, Social Login (Socialite)). Copy it into config/ only
    | when the project requirements explicitly request Social Login, and list
    | only the providers the project requested.
    |
    | Installing the laravel/socialite package does NOT activate Social
    | Login: with an empty enabled_providers list every social endpoint
    | answers 404 and the default Email/Password + Passport flow is the only
    | authentication path.
    |
    */

    // The provider allowlist, e.g. SOCIAL_ENABLED_PROVIDERS=google,github.
    // Only these values are accepted on the social routes; any other provider
    // answers 404. Never enable a provider the project did not request.
    'enabled_providers' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('SOCIAL_ENABLED_PROVIDERS', '')),
    ))),

    // The role assigned to users created through a social provider. Set it to
    // the project's defined default role — the same role the Roles and
    // Permissions Seeder assigns to new users.
    'default_role' => env('SOCIAL_DEFAULT_ROLE', ''),

    // Provider access/refresh tokens are discarded after identity resolution
    // unless the business requirements explicitly require provider API access.
    // When true, store them encrypted on the social_accounts rows (Laravel
    // encrypted casts) — never in plain text.
    'store_provider_tokens' => (bool) env('SOCIAL_STORE_PROVIDER_TOKENS', false),

];
