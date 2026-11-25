<?php

namespace Tests\Feature\V1;

use App\Models\V1\Role;
use App\Models\V1\SocialAccount;
use App\Models\V1\User;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Client;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Tests\TestCase;

/**
 * Social Login (Socialite) spec — OPTIONAL feature template.
 *
 * Copy this test only into projects whose requirements explicitly requested
 * Social Login, with the feature integrated per references/authentication.md
 * (Social Login (Socialite)): the social routes merged into routes/v1/auth.php,
 * config/social.php enabled for the requested providers, and the Auth module
 * social actions in place. Adapt PROVIDER and the default role to the
 * project's requested providers and defined default role.
 *
 * Flow under test (Socialite resolves the identity only; token issuance is
 * the unchanged Passport authorization code + PKCE lifecycle):
 *
 *   1. POST /api/v1/auth/social/{provider}        — provider token + PKCE challenge -> authorization code
 *   2. POST /oauth/token (authorization_code)     — code + verifier -> access + refresh tokens
 *   3. POST /oauth/token (refresh_token)          — rotation: a NEW pair; the old pair revoked
 *
 * The Socialite facade is mocked (references/testing.md §11): no test ever
 * calls a real provider, and the suite passes without provider credentials
 * or network access.
 */
class SocialLoginTest extends TestCase
{
    protected const PROVIDER = 'google';

    protected const PROVIDER_USER_ID = 'google-user-123';

    protected function setUp(): void
    {
        parent::setUp();

        // Enable the provider under test and give it a dedicated default
        // role; replace both with the project's requested provider set and
        // defined default role (config/social.php).
        config([
            'social.enabled_providers' => [self::PROVIDER],
            'social.default_role' => 'social-default',
        ]);

        Role::findOrCreate('social-default', config('auth.defaults.guard'));
    }

    // ------------------------------------------------------------------
    // Socialite mocking: userFromToken() resolves a controlled identity
    // without any network call.
    // ------------------------------------------------------------------

    protected function fakeProvider(string $email, bool $emailVerified = true): void
    {
        $socialUser = new SocialiteUser();
        $socialUser->id = self::PROVIDER_USER_ID;
        $socialUser->name = 'Social User';
        $socialUser->email = $email;
        // The raw provider payload; the feature reads email_verified from it.
        $socialUser->user = [
            'sub' => self::PROVIDER_USER_ID,
            'email' => $email,
            'email_verified' => $emailVerified,
        ];

        $driver = Mockery::mock();
        $driver->shouldReceive('userFromToken')->andReturn($socialUser);

        Socialite::shouldReceive('driver')->with(self::PROVIDER)->andReturn($driver);
    }

    // ------------------------------------------------------------------
    // PKCE helpers (RFC 7636) — identical to AuthTest.
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
     * Call the social login endpoint with a fresh PKCE pair.
     *
     * @return array{response: TestResponse, verifier: string}
     */
    protected function socialLogin(?string $provider = null): array
    {
        $verifier = $this->codeVerifier();

        $response = $this->postJson('/api/v1/auth/social/'.($provider ?? self::PROVIDER), [
            'access_token' => 'provider-access-token',
            'client_id' => $this->firstPartyClientId(),
            'redirect_uri' => config('app.url'),
            'code_challenge' => $this->codeChallenge($verifier),
            'code_challenge_method' => 'S256',
        ]);

        return ['response' => $response, 'verifier' => $verifier];
    }

    /**
     * Exchange an authorization code at Passport's unchanged token endpoint.
     */
    protected function exchange(array $login): TestResponse
    {
        return $this->postJson('/oauth/token', [
            'grant_type' => 'authorization_code',
            'client_id' => $this->firstPartyClientId(),
            'redirect_uri' => config('app.url'),
            'code' => $login['response']->json('data.code'),
            'code_verifier' => $login['verifier'],
        ]);
    }

    // ------------------------------------------------------------------
    // Tests
    // ------------------------------------------------------------------

    public function test_social_login_issues_a_code_and_the_token_pair(): void
    {
        $this->fakeProvider('new-social-user@example.local', true);

        $login = $this->socialLogin();
        $login['response']->assertOk()
            ->assertJsonPath('status_code', 200)
            ->assertJsonPath('message', 'Logged in successfully.');
        $this->assertNotEmpty(
            $login['response']->json('data.code'),
            'Social login must return an authorization code in data.code.'
        );

        $exchange = $this->exchange($login);
        $exchange->assertOk()
            ->assertJsonStructure(['token_type', 'expires_in', 'access_token', 'refresh_token']);

        $user = User::where('email', 'new-social-user@example.local')->sole();
        $this->assertNull($user->password, 'Social-only users must have no password.');
        $this->assertNotNull($user->email_verified_at, 'Provider-verified emails must be marked verified.');
        $this->assertTrue(
            $user->hasRole(config('social.default_role')),
            'New social users must receive the project default role.'
        );
        $this->assertTrue(
            SocialAccount::where('user_id', $user->id)
                ->where('provider', self::PROVIDER)
                ->where('provider_user_id', self::PROVIDER_USER_ID)
                ->exists()
        );
    }

    public function test_refresh_rotates_the_pair_from_a_social_login(): void
    {
        $this->fakeProvider('rotating-social-user@example.local', true);

        $login = $this->socialLogin();
        $exchange = $this->exchange($login);
        $exchange->assertOk();

        $oldAccess = $exchange->json('access_token');
        $oldRefresh = $exchange->json('refresh_token');

        $refresh = $this->postJson('/oauth/token', [
            'grant_type' => 'refresh_token',
            'client_id' => $this->firstPartyClientId(),
            'refresh_token' => $oldRefresh,
        ]);
        $refresh->assertOk()
            ->assertJsonStructure(['token_type', 'expires_in', 'access_token', 'refresh_token']);

        $this->assertNotSame($oldAccess, $refresh->json('access_token'));
        $this->assertNotSame($oldRefresh, $refresh->json('refresh_token'));

        // The rotated-out pair no longer authenticates.
        $this->withToken($oldAccess)->getJson('/api/v1/auth/me')->assertUnauthorized();
    }

    public function test_verified_provider_email_autolinks_the_existing_user(): void
    {
        $existing = User::factory()->create(['email' => 'existing@example.local']);

        $this->fakeProvider('existing@example.local', true);
        $this->socialLogin()->response->assertOk();

        $this->assertSame(
            $existing->id,
            User::where('email', 'existing@example.local')->sole()->id,
            'A provider-verified email must auto-link, never duplicate the account.'
        );
        $this->assertTrue(
            SocialAccount::where('user_id', $existing->id)->where('provider', self::PROVIDER)->exists()
        );
    }

    public function test_unverified_provider_email_is_rejected_for_an_existing_user(): void
    {
        User::factory()->create(['email' => 'existing@example.local']);

        $this->fakeProvider('existing@example.local', false);
        $this->socialLogin()->response->assertStatus(422);

        $this->assertSame(0, SocialAccount::count(), 'No link may be created from an unverified email.');
    }

    public function test_unrequested_provider_answers_404(): void
    {
        // setUp() enables only 'google'; no Socialite mock is installed, so a
        // 404 must be answered before any provider interaction.
        $this->postJson('/api/v1/auth/social/github', [
            'access_token' => 'provider-access-token',
            'client_id' => $this->firstPartyClientId(),
            'redirect_uri' => config('app.url'),
            'code_challenge' => $this->codeChallenge($this->codeVerifier()),
            'code_challenge_method' => 'S256',
        ])->assertStatus(404);
    }

    public function test_link_attaches_the_identity_to_the_authenticated_user(): void
    {
        $user = User::factory()->create(['email' => 'me@example.local']);

        $this->fakeProvider('me@example.local', true);
        $this->actingAs($user, 'api')
            ->postJson('/api/v1/auth/social/'.self::PROVIDER.'/link', [
                'access_token' => 'provider-access-token',
            ])
            ->assertOk();

        $this->assertTrue(
            SocialAccount::where('user_id', $user->id)->where('provider', self::PROVIDER)->exists()
        );
    }

    public function test_link_rejects_an_identity_owned_by_another_user(): void
    {
        $other = User::factory()->create();
        SocialAccount::create([
            'user_id' => $other->id,
            'provider' => self::PROVIDER,
            'provider_user_id' => self::PROVIDER_USER_ID,
        ]);

        $user = User::factory()->create();
        $this->fakeProvider('whatever@example.local', true);

        $this->actingAs($user, 'api')
            ->postJson('/api/v1/auth/social/'.self::PROVIDER.'/link', [
                'access_token' => 'provider-access-token',
            ])
            ->assertStatus(422);

        $this->assertSame(1, SocialAccount::count());
    }

    public function test_unlink_is_blocked_when_it_leaves_no_login_method(): void
    {
        $user = User::factory()->create(['password' => null]);
        SocialAccount::create([
            'user_id' => $user->id,
            'provider' => self::PROVIDER,
            'provider_user_id' => self::PROVIDER_USER_ID,
        ]);

        $this->actingAs($user, 'api')
            ->deleteJson('/api/v1/auth/social/'.self::PROVIDER)
            ->assertStatus(422);

        $this->assertTrue(
            SocialAccount::where('user_id', $user->id)->exists(),
            'The last login method must stay linked.'
        );
    }
}
