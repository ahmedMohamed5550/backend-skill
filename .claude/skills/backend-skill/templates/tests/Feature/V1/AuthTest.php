<?php

namespace Tests\Feature\V1;

use App\Models\V1\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Client;
use Laravel\Passport\Passport;
use Laravel\Passport\RefreshToken;
use Laravel\Passport\Token;
use Tests\TestCase;

/**
 * Access Token and Refresh Token lifecycle spec.
 *
 * Flow under test (Passport 13, authorization code grant with PKCE using the
 * seeded public first-party client):
 *
 *   1. POST /api/v1/auth/login        — credentials + PKCE challenge -> authorization code
 *   2. POST /oauth/token (auth code)  — code + verifier -> access + refresh tokens
 *   3. POST /oauth/token (refresh)    — refresh token -> NEW pair (rotation; old tokens revoked);
 *                                      refresh failures answer invalid_grant with HTTP 400
 *   4. POST /api/v1/auth/logout       — revokes the current access token AND its refresh token
 *
 * Passport route hardening: automatic Passport routes are disabled
 * (Passport::ignoreRoutes() in AppServiceProvider::register()); the only
 * registered OAuth endpoint is POST /oauth/token, registered by the skill in
 * AppServiceProvider::boot() with Passport's AccessTokenController. Every
 * other Passport route (browser authorize flow, consent, transient token
 * refresh, personal tokens, scopes, device grant) must answer 404.
 *
 * Rate limiting (references/api-standards.md, Rate Limiting): the login
 * endpoint runs under the throttle:auth limiter — 5 attempts per minute and
 * 30 per hour, enforced independently per normalized email and per IP; the
 * 429 response carries the Retry-After header and the approved envelope.
 * POST /oauth/token keeps Passport's own throttle middleware (60 per minute
 * per IP).
 *
 * Expiry is enforced by Passport/league against the real system clock
 * (league uses time(); there is no injectable clock), so the expiry tests
 * issue 1-second tokens and sleep(2) — the one sanctioned exception to the
 * no-sleep rule (references/testing.md §10). Rate-limit counters live in
 * the cache; phpunit.xml's array store isolates them per test, so the
 * throttled requests in these tests never leak across tests.
 */
class AuthTest extends TestCase
{
    /**
     * The authenticated user used by the token lifecycle tests.
     */
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // The approved UserFactory hashes 'password' as the default password.
        $this->user = User::factory()->create();
    }

    protected function tearDown(): void
    {
        // Restore the production token TTLs from config/oauth.php so a
        // short-lived override in one test never leaks into another test
        // (Passport TTLs live in static state; references/testing.md §10).
        Passport::tokensExpireIn(now()->addMinutes((int) config('oauth.access_token_expires_in')));
        Passport::refreshTokensExpireIn(now()->addDays((int) config('oauth.refresh_token_expires_in')));

        // travel() (used by the per-hour throttle test) writes static Carbon
        // test-now state; clear it so it never leaks into other tests.
        Carbon::setTestNow(null);

        parent::tearDown();
    }

    // ------------------------------------------------------------------
    // PKCE helpers (RFC 7636): the client generates the verifier and sends
    // only the S256 challenge to the login endpoint; the token exchange
    // proves possession of the verifier. Frontends implement the same pair.
    // ------------------------------------------------------------------

    protected function codeVerifier(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(48)), '+/', '-_'), '=');
    }

    protected function codeChallenge(string $verifier): string
    {
        return rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
    }

    protected function firstPartyClientId(): string
    {
        return Client::where('name', 'First Party PKCE Client')
            ->where('revoked', false)
            ->sole()
            ->id;
    }

    /**
     * Call the login endpoint with a fresh PKCE pair.
     *
     * @return array{response: TestResponse, verifier: string, clientId: string}
     */
    protected function login(): array
    {
        $verifier = $this->codeVerifier();
        $clientId = $this->firstPartyClientId();

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $this->user->email,
            'password' => 'password',
            'client_id' => $clientId,
            'redirect_uri' => config('app.url'),
            'code_challenge' => $this->codeChallenge($verifier),
            'code_challenge_method' => 'S256',
        ]);

        return ['response' => $response, 'verifier' => $verifier, 'clientId' => $clientId];
    }

    /**
     * Exchange an authorization code at Passport's token endpoint.
     */
    protected function exchange(array $login, ?string $code = null, ?string $verifier = null): TestResponse
    {
        return $this->postJson('/oauth/token', [
            'grant_type' => 'authorization_code',
            'client_id' => $login['clientId'],
            'redirect_uri' => config('app.url'),
            'code' => $code ?? $login['response']->json('data.code'),
            'code_verifier' => $verifier ?? $login['verifier'],
        ]);
    }

    /**
     * Run the complete login flow and return the issued token payload.
     *
     * @return array{payload: array, clientId: string, access_token: string, refresh_token: string}
     */
    protected function loginAndExchange(): array
    {
        $login = $this->login();
        $response = $this->exchange($login);
        $response->assertOk();

        return [
            'payload' => $response->json(),
            'clientId' => $login['clientId'],
            'access_token' => $response->json('access_token'),
            'refresh_token' => $response->json('refresh_token'),
        ];
    }

    /**
     * Refresh a token pair at Passport's token endpoint.
     */
    protected function refresh(string $refreshToken, string $clientId): TestResponse
    {
        return $this->postJson('/oauth/token', [
            'grant_type' => 'refresh_token',
            'client_id' => $clientId,
            'refresh_token' => $refreshToken,
        ]);
    }

    public function test_login_issues_an_authorization_code(): void
    {
        $login = $this->login();

        $login['response']->assertOk()
            ->assertJsonPath('status_code', 200)
            ->assertJsonPath('message', 'Logged in successfully.');

        $this->assertNotEmpty(
            $login['response']->json('data.code'),
            'Login must return an authorization code in data.code.'
        );
    }

    public function test_login_requires_a_pkce_code_challenge(): void
    {
        $this->postJson('/api/v1/auth/login', [
            'email' => $this->user->email,
            'password' => 'password',
            'client_id' => $this->firstPartyClientId(),
            'redirect_uri' => config('app.url'),
        ])->assertStatus(422);
    }

    public function test_login_rejects_wrong_credentials(): void
    {
        $this->postJson('/api/v1/auth/login', $this->loginPayload())
            ->assertStatus(422);
    }

    // ------------------------------------------------------------------
    // Rate limiting (references/api-standards.md, Rate Limiting): the
    // throttle:auth limiter counts every login attempt — it runs before
    // the controller — with two independent buckets per normalized email
    // and per IP, each with a per-minute and a per-hour window.
    // ------------------------------------------------------------------

    /**
     * A wrong-password login payload: cheap (no authorization code is
     * issued) and still counted by throttle:auth.
     */
    protected function loginPayload(?string $email = null): array
    {
        return [
            'email' => $email ?? $this->user->email,
            'password' => 'wrong-password',
            'client_id' => $this->firstPartyClientId(),
            'redirect_uri' => config('app.url'),
            'code_challenge' => $this->codeChallenge($this->codeVerifier()),
            'code_challenge_method' => 'S256',
        ];
    }

    public function test_login_is_throttled_to_five_attempts_per_minute(): void
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/api/v1/auth/login', $this->loginPayload())
                ->assertStatus(422);
        }

        $this->postJson('/api/v1/auth/login', $this->loginPayload())
            ->assertStatus(429)
            ->assertJsonPath('status_code', 429)
            ->assertHeader('Retry-After')
            ->assertHeader('X-RateLimit-Remaining', 0);
    }

    public function test_login_email_limit_is_independent_of_the_ip(): void
    {
        // Exhaust the email bucket from one IP.
        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.1']);
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/api/v1/auth/login', $this->loginPayload())
                ->assertStatus(422);
        }

        // A different IP does not reset the exhausted email bucket.
        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.2']);
        $this->postJson('/api/v1/auth/login', $this->loginPayload())
            ->assertStatus(429);

        // A different email from the same new IP has fresh email and IP
        // buckets and reaches credential validation again.
        $this->postJson('/api/v1/auth/login', $this->loginPayload('other@example.local'))
            ->assertStatus(422);
    }

    public function test_login_ip_limit_is_independent_of_the_email(): void
    {
        // Exhaust the IP bucket with five different (unknown) addresses;
        // each attempt still counts against the IP bucket.
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->postJson('/api/v1/auth/login', $this->loginPayload("attacker{$attempt}@example.local"))
                ->assertStatus(422);
        }

        // A sixth address from the same IP is rejected although its own
        // email bucket is fresh.
        $this->postJson('/api/v1/auth/login', $this->loginPayload('sixth@example.local'))
            ->assertStatus(429)
            ->assertHeader('Retry-After');
    }

    public function test_login_email_keys_are_normalized(): void
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/api/v1/auth/login', $this->loginPayload())
                ->assertStatus(422);
        }

        // An upper-cased variant of the same address resolves to the same
        // (exhausted) email bucket — normalization defeats case rotation.
        $this->postJson('/api/v1/auth/login', $this->loginPayload(strtoupper($this->user->email)))
            ->assertStatus(429);
    }

    // ------------------------------------------------------------------
    // Input robustness: throttle:auth runs BEFORE validation, so its
    // normalization must tolerate non-string input without crashing the
    // request — garbage input gets the standard 422 validation answer.
    // ------------------------------------------------------------------

    public function test_login_rejects_a_non_string_email_with_422(): void
    {
        $this->postJson('/api/v1/auth/login', [
            'email' => ['not-an-email'],
            'password' => 'password',
            'client_id' => $this->firstPartyClientId(),
            'redirect_uri' => config('app.url'),
            'code_challenge' => $this->codeChallenge($this->codeVerifier()),
            'code_challenge_method' => 'S256',
        ])->assertStatus(422);
    }

    public function test_login_is_throttled_to_thirty_attempts_per_hour(): void
    {
        // Six rounds of five attempts, one minute apart: every per-minute
        // bucket expires between rounds, so only the per-hour buckets
        // accumulate. The rate-limit counters live in the cache, whose
        // expiry is Carbon-based, so travel() advances them
        // deterministically — unlike the OAuth expiry tests, which cannot
        // use time travel because league reads the real clock
        // (references/testing.md §10).
        for ($round = 0; $round < 6; $round++) {
            for ($attempt = 0; $attempt < 5; $attempt++) {
                $this->postJson('/api/v1/auth/login', $this->loginPayload())
                    ->assertStatus(422);
            }

            $this->travel(61)->seconds();
        }

        // The 31st attempt within the hour: the per-minute buckets are
        // fresh, the per-hour buckets are exhausted (30). The 429 headers
        // describe the tripped hour window.
        $this->postJson('/api/v1/auth/login', $this->loginPayload())
            ->assertStatus(429)
            ->assertHeader('Retry-After')
            ->assertHeader('X-RateLimit-Limit', 30)
            ->assertHeader('X-RateLimit-Remaining', 0)
            ->assertJsonPath('status_code', 429);
    }

    // ------------------------------------------------------------------
    // The `api` baseline limiter ($middleware->throttleApi()): every
    // /api/v1 request is limited to api_per_minute (default 60) per
    // authenticated user id, or per client IP for guests — independent of
    // the auth limiter on the login route.
    // ------------------------------------------------------------------

    public function test_api_baseline_throttles_guest_requests_per_ip(): void
    {
        for ($attempt = 0; $attempt < 60; $attempt++) {
            $this->getJson('/api/v1/auth/me')->assertStatus(401);
        }

        $this->getJson('/api/v1/auth/me')
            ->assertStatus(429)
            ->assertJsonPath('status_code', 429)
            ->assertHeader('Retry-After');
    }

    public function test_api_baseline_throttles_per_authenticated_user(): void
    {
        $this->actingAs($this->user, 'api');

        for ($attempt = 0; $attempt < 60; $attempt++) {
            $this->getJson('/api/v1/auth/me')->assertOk();
        }

        $this->getJson('/api/v1/auth/me')->assertStatus(429);
    }

    public function test_token_endpoint_is_throttled_to_sixty_requests_per_minute(): void
    {
        // Passport's own throttle middleware on POST /oauth/token: 60 token
        // requests per minute per IP (references/api-standards.md, Rate
        // Limiting). Invalid-code bodies fail fast with 400 invalid_grant
        // until the limiter trips; the 429 keeps the approved envelope and
        // its throttle headers even outside the api/v1 prefix.
        $payload = [
            'grant_type' => 'authorization_code',
            'client_id' => $this->firstPartyClientId(),
            'redirect_uri' => config('app.url'),
            'code' => 'not-a-real-code',
            'code_verifier' => $this->codeVerifier(),
        ];

        for ($attempt = 0; $attempt < 60; $attempt++) {
            $this->postJson('/oauth/token', $payload)->assertStatus(400);
        }

        $this->postJson('/oauth/token', $payload)
            ->assertStatus(429)
            ->assertJsonPath('status_code', 429)
            ->assertJsonPath('message', 'Too many attempts. Please try again later.')
            ->assertHeader('Retry-After');
    }

    public function test_token_exchange_issues_access_and_refresh_tokens(): void
    {
        $result = $this->loginAndExchange();

        $payload = $result['payload'];

        $this->assertSame('Bearer', $payload['token_type']);
        $this->assertNotEmpty($payload['access_token']);
        $this->assertNotEmpty(
            $payload['refresh_token'],
            'The authorization code exchange must issue a refresh token.'
        );
        $this->assertGreaterThan(0, $payload['expires_in']);

        // The refresh token must exist in the database, linked to its access
        // token (oauth_refresh_tokens.access_token_id), and both stay active.
        $accessToken = Token::where('user_id', $this->user->id)->where('revoked', false)->sole();
        $refreshToken = RefreshToken::where('access_token_id', $accessToken->id)->sole();

        $this->assertFalse((bool) $refreshToken->revoked);
        $this->assertNotNull($refreshToken->expires_at);
    }

    // ------------------------------------------------------------------
    // Token request contract (RFC 6749 §4.1.3): the login includes
    // redirect_uri, so the exchange must repeat it byte-identically — and
    // client_id is required for both grants. Documented in README.md and
    // the Swagger annotation: omitting any field answers 400
    // invalid_request with a hint naming the missing parameter.
    // ------------------------------------------------------------------

    public function test_exchange_requires_the_login_redirect_uri(): void
    {
        $login = $this->login();
        $code = $login['response']->json('data.code');

        // Omitting redirect_uri fails the whole exchange with the hint
        // naming the missing parameter.
        $this->postJson('/oauth/token', [
            'grant_type' => 'authorization_code',
            'client_id' => $login['clientId'],
            'code' => $code,
            'code_verifier' => $login['verifier'],
        ])->assertStatus(400)
            ->assertJsonPath('error', 'invalid_request')
            ->assertJsonPath('hint', 'Check the `redirect_uri` parameter');

        // The failed attempt did not consume the code: the documented flow
        // succeeds unchanged right after.
        $this->exchange($login)->assertOk();
    }

    public function test_exchange_rejects_a_foreign_redirect_uri(): void
    {
        $login = $this->login();

        $this->postJson('/oauth/token', [
            'grant_type' => 'authorization_code',
            'client_id' => $login['clientId'],
            'redirect_uri' => 'http://localhost:3000/callback',
            'code' => $login['response']->json('data.code'),
            'code_verifier' => $login['verifier'],
        ])->assertStatus(400)
            ->assertJsonPath('error', 'invalid_request')
            ->assertJsonPath('hint', 'Invalid redirect URI');
    }

    public function test_refresh_requires_the_client_id(): void
    {
        $result = $this->loginAndExchange();

        $this->postJson('/oauth/token', [
            'grant_type' => 'refresh_token',
            'refresh_token' => $result['refresh_token'],
        ])->assertStatus(400)
            ->assertJsonPath('error', 'invalid_request')
            ->assertJsonPath('hint', 'Check the `client_id` parameter');
    }

    public function test_refresh_grant_rotates_tokens(): void
    {
        $result = $this->loginAndExchange();

        $oldAccessToken = Token::where('user_id', $this->user->id)->where('revoked', false)->sole();
        $oldRefreshToken = RefreshToken::where('access_token_id', $oldAccessToken->id)->sole();

        $response = $this->refresh($result['refresh_token'], $result['clientId']);
        $response->assertOk();

        // A NEW pair is issued: neither value may be reused.
        $this->assertNotSame($result['access_token'], $response->json('access_token'));
        $this->assertNotSame($result['refresh_token'], $response->json('refresh_token'));
        $this->assertNotEmpty($response->json('refresh_token'), 'Rotation must issue a new refresh token.');

        // The OLD access token and OLD refresh token are revoked.
        $this->assertTrue((bool) $oldAccessToken->fresh()->revoked, 'Rotation must revoke the old access token.');
        $this->assertTrue((bool) $oldRefreshToken->fresh()->revoked, 'Rotation must revoke the old refresh token.');

        // Exactly one active pair remains.
        $newAccessToken = Token::where('user_id', $this->user->id)->where('revoked', false)->sole();
        $newRefreshToken = RefreshToken::where('access_token_id', $newAccessToken->id)->where('revoked', false)->sole();

        $this->assertNotSame($oldAccessToken->id, $newAccessToken->id);
        $this->assertNotSame($oldRefreshToken->id, $newRefreshToken->id);
    }

    public function test_used_refresh_token_cannot_be_reused(): void
    {
        $result = $this->loginAndExchange();

        $this->refresh($result['refresh_token'], $result['clientId'])->assertOk();

        // Replaying the consumed refresh token must fail (rotation) with
        // the OAuth invalid_grant error (HTTP 400, league convention).
        $this->refresh($result['refresh_token'], $result['clientId'])
            ->assertStatus(400)
            ->assertJsonPath('error', 'invalid_grant');
    }

    public function test_refresh_rejects_an_invalid_refresh_token(): void
    {
        $result = $this->loginAndExchange();

        $this->refresh('not-a-real-refresh-token', $result['clientId'])
            ->assertStatus(400)
            ->assertJsonPath('error', 'invalid_grant');
    }

    public function test_refresh_rejects_an_unknown_client(): void
    {
        $result = $this->loginAndExchange();

        $this->refresh($result['refresh_token'], 'unknown-client-id')
            ->assertStatus(401);
    }

    public function test_expired_access_token_returns_401(): void
    {
        Passport::tokensExpireIn(now()->addSeconds(1));

        $result = $this->loginAndExchange();

        sleep(2); // league validates the JWT exp claim against time(); see class docblock.

        $this->getJson('/api/v1/auth/me', ['Authorization' => 'Bearer '.$result['access_token']])
            ->assertStatus(401);
    }

    public function test_expired_refresh_token_is_rejected(): void
    {
        Passport::refreshTokensExpireIn(now()->addSeconds(1));

        $result = $this->loginAndExchange();

        sleep(2); // league compares the payload expire_time with time(); see class docblock.

        $this->refresh($result['refresh_token'], $result['clientId'])
            ->assertStatus(400)
            ->assertJsonPath('error', 'invalid_grant');
    }

    public function test_logout_revokes_access_and_refresh_tokens(): void
    {
        $result = $this->loginAndExchange();

        $accessToken = Token::where('user_id', $this->user->id)->where('revoked', false)->sole();
        $refreshToken = RefreshToken::where('access_token_id', $accessToken->id)->sole();

        $this->postJson('/api/v1/auth/logout', [], ['Authorization' => 'Bearer '.$result['access_token']])
            ->assertOk()
            ->assertJsonPath('status_code', 200)
            ->assertJsonPath('message', 'Logged out successfully.');

        // Both the access token AND its associated refresh token are revoked.
        $this->assertTrue((bool) $accessToken->fresh()->revoked, 'Logout must revoke the access token.');
        $this->assertTrue((bool) $refreshToken->fresh()->revoked, 'Logout must revoke the associated refresh token.');

        // The revoked access token no longer authenticates (guard re-validates).
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/v1/auth/me', ['Authorization' => 'Bearer '.$result['access_token']])
            ->assertStatus(401);

        // The revoked refresh token can no longer be refreshed.
        $this->refresh($result['refresh_token'], $result['clientId'])
            ->assertStatus(400)
            ->assertJsonPath('error', 'invalid_grant');
    }

    public function test_me_requires_authentication(): void
    {
        $this->getJson('/api/v1/auth/me')->assertStatus(401);
    }

    // ------------------------------------------------------------------
    // Passport route hardening: only POST /oauth/token exists. The browser
    // authorize flow is forbidden by the skill's S256-only PKCE rule —
    // league's authorize endpoint accepts the default plain verifier — and
    // the consent, transient-token, personal-token, scope, and device routes
    // have no first-party consumer. All of them must answer 404.
    // ------------------------------------------------------------------

    public function test_oauth_token_is_the_only_registered_passport_route(): void
    {
        // The single manually registered route backs both lifecycle grants
        // (authorization_code and refresh_token — exercised by the tests
        // above); the endpoint exists and rejects other methods.
        $this->assertTrue(Route::has('passport.token'));
        $this->getJson('/oauth/token')->assertStatus(405);

        // The endpoint keeps Passport's own throttle middleware: 60 token
        // requests per minute per IP (references/api-standards.md, Rate
        // Limiting).
        $route = Route::getRoutes()->getByName('passport.token');
        $this->assertNotNull($route);
        $this->assertContains('throttle', $route->gatherMiddleware());
    }

    public function test_unused_passport_routes_return_404(): void
    {
        $this->getJson('/oauth/authorize')->assertNotFound();
        $this->postJson('/oauth/authorize')->assertNotFound();
        $this->deleteJson('/oauth/authorize')->assertNotFound();
        $this->postJson('/oauth/token/refresh')->assertNotFound();
        $this->getJson('/oauth/tokens')->assertNotFound();
        $this->deleteJson('/oauth/tokens/1')->assertNotFound();
        $this->getJson('/oauth/scopes')->assertNotFound();
        $this->getJson('/oauth/device')->assertNotFound();
        $this->postJson('/oauth/device')->assertNotFound();
        $this->postJson('/oauth/device/code')->assertNotFound();
    }
}
