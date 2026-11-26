<?php

namespace Tests\Feature\V1;

use App\Models\V1\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Laravel\Passport\Client;
use Tests\TestCase;

/**
 * Email verification spec (Laravel native MustVerifyEmail flow —
 * references/authentication.md, Email Verification).
 *
 * Flow under test:
 *
 *   1. GET /api/v1/auth/email/verify/{uuid}/{hash} — the signed verification
 *      URL the framework's VerifyEmail notification emails (route name
 *      verification.verify; the VerifyEmail::createUrlUsing() hook in the
 *      AppServiceProvider builds the temporarySignedRoute URL with the
 *      user's uuid — the public API identifier — instead of the database
 *      id). The ValidateSignature middleware rejects expired or tampered
 *      links with 403 before the controller runs; the controller resolves
 *      the user by uuid, checks the hash against sha1(email), then
 *      markEmailAsVerified() stamps email_verified_at and dispatches the
 *      native Verified event.
 *   2. POST /api/v1/auth/email/resend (auth:api) — re-sends the native
 *      notification for the authenticated user; already-verified accounts
 *      are rejected with 422. Authentication runs through the real
 *      authorization code + PKCE login flow (references/testing.md §9).
 *
 * Emails are intercepted with Notification::fake() (references/testing.md
 * §11): the suite never sends real mail.
 */
class EmailVerificationTest extends TestCase
{
    /**
     * Build the signed verification URL exactly the way the framework's
     * VerifyEmail notification does (temporarySignedRoute for the
     * verification.verify route with the uuid parameter — see the
     * VerifyEmail::createUrlUsing() hook in the AppServiceProvider), and
     * return its path + query for the test client.
     */
    protected function signedVerificationUrl(User $user, $expiry = null): string
    {
        $url = URL::temporarySignedRoute(
            'verification.verify',
            $expiry ?? now()->addMinutes(config('auth.verification.expire', 60)),
            [
                'uuid' => $user->uuid,
                'hash' => sha1($user->getEmailForVerification()),
            ],
        );

        return parse_url($url, PHP_URL_PATH).'?'.parse_url($url, PHP_URL_QUERY);
    }

    /**
     * Run the real login flow for the given user and return the access
     * token (references/testing.md §9 — no createToken shortcuts).
     */
    protected function accessTokenFor(User $user): string
    {
        $verifier = rtrim(strtr(base64_encode(random_bytes(48)), '+/', '-_'), '=');

        $login = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password',
            'client_id' => Client::where('name', 'First Party PKCE Client')->sole()->id,
            'redirect_uri' => config('app.url'),
            'code_challenge' => rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '='),
            'code_challenge_method' => 'S256',
        ])->assertOk();

        $exchange = $this->postJson('/oauth/token', [
            'grant_type' => 'authorization_code',
            'client_id' => Client::where('name', 'First Party PKCE Client')->sole()->id,
            'redirect_uri' => config('app.url'),
            'code' => $login->json('data.code'),
            'code_verifier' => $verifier,
        ])->assertOk();

        return (string) $exchange->json('access_token');
    }

    // ------------------------------------------------------------------
    // GET /api/v1/auth/email/verify/{uuid}/{hash}
    // ------------------------------------------------------------------

    public function test_verification_link_verifies_the_email(): void
    {
        Notification::fake();

        $user = User::factory()->unverified()->create();

        $this->assertFalse($user->hasVerifiedEmail());

        $this->getJson($this->signedVerificationUrl($user))
            ->assertOk()
            ->assertJsonPath('status_code', 200)
            ->assertJsonPath('message', 'The email address has been verified successfully.');

        // The database actually changed: email_verified_at is stamped
        // (references/testing.md §5.1, database state assertions).
        $fresh = $user->fresh();

        $this->assertTrue($fresh->hasVerifiedEmail());
        $this->assertNotNull($fresh->email_verified_at);
    }

    public function test_verification_link_is_idempotent(): void
    {
        Notification::fake();

        $user = User::factory()->create(); // already verified

        $this->getJson($this->signedVerificationUrl($user))
            ->assertOk()
            ->assertJsonPath('status_code', 200);
    }

    public function test_expired_verification_link_is_rejected_with_403(): void
    {
        Notification::fake();

        $user = User::factory()->unverified()->create();

        // A signed URL whose expiry is already in the past: the signature
        // itself is valid, the ValidateSignature middleware rejects it.
        $expired = $this->signedVerificationUrl($user, now()->subMinutes(2));

        $this->getJson($expired)
            ->assertStatus(403)
            ->assertJsonPath('status_code', 403)
            ->assertJsonPath('message', 'This verification link is invalid or has expired.');

        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_tampered_verification_link_is_rejected_with_403(): void
    {
        Notification::fake();

        $user = User::factory()->unverified()->create();

        // A valid signature over a different user: swapping the uuid onto
        // another account's link breaks the signature.
        $other = User::factory()->unverified()->create();
        $tampered = str_replace(
            "verify/{$other->uuid}/",
            "verify/{$user->uuid}/",
            $this->signedVerificationUrl($other),
        );

        $this->getJson($tampered)->assertStatus(403);

        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_verification_link_with_a_wrong_hash_returns_422(): void
    {
        Notification::fake();

        $user = User::factory()->unverified()->create();

        // A cryptographically valid signature over a hash that does not
        // match the addressed user's email: past the middleware, rejected
        // by the controller's hash check.
        $url = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['uuid' => $user->uuid, 'hash' => sha1('someone-else@example.local')],
        );
        $pathQuery = parse_url($url, PHP_URL_PATH).'?'.parse_url($url, PHP_URL_QUERY);

        $this->getJson($pathQuery)
            ->assertStatus(422)
            ->assertJsonPath('status_code', 422);

        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_verification_link_for_an_unknown_user_returns_404(): void
    {
        Notification::fake();

        $user = User::factory()->unverified()->create();

        // A well-signed link whose uuid matches no user: past the signature
        // check, the controller's uuid lookup answers 404.
        $url = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['uuid' => Str::uuid()->toString(), 'hash' => sha1('ghost@example.local')],
        );
        $pathQuery = parse_url($url, PHP_URL_PATH).'?'.parse_url($url, PHP_URL_QUERY);

        $this->getJson($pathQuery)
            ->assertStatus(404)
            ->assertJsonPath('status_code', 404);

        // Nothing else was verified.
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    // ------------------------------------------------------------------
    // POST /api/v1/auth/email/resend (auth:api)
    // ------------------------------------------------------------------

    public function test_resend_sends_the_verification_notification(): void
    {
        Notification::fake();

        $user = User::factory()->unverified()->create();
        $token = $this->accessTokenFor($user);

        $this->postJson('/api/v1/auth/email/resend', [], ['Authorization' => 'Bearer '.$token])
            ->assertOk()
            ->assertJsonPath('status_code', 200)
            ->assertJsonPath('message', 'Verification email sent successfully.');

        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_resend_rejects_an_already_verified_user(): void
    {
        Notification::fake();

        $user = User::factory()->create(); // already verified
        $token = $this->accessTokenFor($user);

        $this->postJson('/api/v1/auth/email/resend', [], ['Authorization' => 'Bearer '.$token])
            ->assertStatus(422)
            ->assertJsonPath('status_code', 422);

        Notification::assertNothingSent();
    }

    public function test_resend_requires_authentication(): void
    {
        $this->postJson('/api/v1/auth/email/resend')->assertStatus(401);
    }
}
