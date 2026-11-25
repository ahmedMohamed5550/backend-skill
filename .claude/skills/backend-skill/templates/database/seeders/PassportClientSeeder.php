<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Laravel\Passport\Client;

/**
 * Creates the two OAuth clients the token lifecycle requires:
 *
 *   1. "Personal Access Client"  — resolves User::createToken() calls.
 *   2. "First Party PKCE Client" — the public OAuth client the login flow
 *      uses for the authorization code + refresh token grants
 *      (references/authentication.md, Token Lifecycle).
 *
 * This seeder is the authoritative creator of both clients in every database
 * (main and test): it is registered in DatabaseSeeder, so `php artisan
 * db:seed` creates them wherever they are missing. Do not create clients
 * with `passport:client` — that bypasses the seeder and masks a broken
 * client definition.
 *
 * Column contract (Passport 13): the `oauth_clients` table has
 * `redirect_uris` and `grant_types` (both JSON-cast arrays), a nullable
 * `secret` (null = public client), a nullable `provider`, and `revoked`.
 * Older columns such as `redirect` and `public` no longer exist.
 *
 * The first-party client is PUBLIC (secret = null): public frontends only
 * ever hold its id, never a secret, and the authorization code grant is
 * protected by PKCE instead of a client secret.
 */
class PassportClientSeeder extends Seeder
{
    /**
     * The names used to look the clients up (idempotent across re-seeds).
     */
    protected const CLIENT_NAME = 'Personal Access Client';

    protected const FIRST_PARTY_CLIENT_NAME = 'First Party PKCE Client';

    public function run(): void
    {
        Client::firstOrCreate(
            ['name' => self::CLIENT_NAME],
            [
                // personalAccessClient() resolution requires: revoked = false,
                // provider null (or matching auth.guards.api.provider), and
                // grant_types containing 'personal_access'.
                'redirect_uris' => [config('app.url')],
                'grant_types' => ['personal_access'],
                'secret' => null,
                'provider' => null,
                'revoked' => false,
            ],
        );

        Client::firstOrCreate(
            ['name' => self::FIRST_PARTY_CLIENT_NAME],
            [
                // The login flow validates the client id against this row and
                // exchanges codes/refresh tokens for it at POST /oauth/token.
                // grant_types must contain exactly the two grants the JSON
                // first-party flow uses: no password grant, no implicit grant.
                'redirect_uris' => [config('app.url')],
                'grant_types' => ['authorization_code', 'refresh_token'],
                'secret' => null,
                'provider' => null,
                'revoked' => false,
            ],
        );
    }
}
