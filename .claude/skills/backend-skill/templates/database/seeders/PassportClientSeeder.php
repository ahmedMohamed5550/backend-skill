<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Laravel\Passport\Client;

/**
 * Creates the Passport personal access client required to issue tokens
 * through User::createToken() (the approved login flow).
 *
 * This seeder is the authoritative creator of the personal access client in
 * every database (main and test): it is registered in DatabaseSeeder, so
 * `php artisan db:seed` creates it wherever it is missing. Do not create the
 * client with `passport:client --personal` — that bypasses the seeder and
 * masks a broken client definition.
 *
 * Column contract (Passport 13): the `oauth_clients` table has
 * `redirect_uris` and `grant_types` (both JSON-cast arrays), a nullable
 * `secret` (null = public client), a nullable `provider`, and `revoked`.
 * Older columns such as `redirect` and `public` no longer exist.
 */
class PassportClientSeeder extends Seeder
{
    /**
     * The name used to look the client up (idempotent across re-seeds).
     */
    protected const CLIENT_NAME = 'Personal Access Client';

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
    }
}
