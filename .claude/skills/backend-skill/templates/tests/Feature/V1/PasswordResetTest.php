<?php

namespace Tests\Feature\V1;

use App\Models\V1\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Client;
use Tests\TestCase;

/**
 * Password reset spec (Laravel native Password broker —
 * references/authentication.md, Password Reset).
 *
 * Flow under test:
 *
 *   1. POST /api/v1/auth/forgot-password — sends the native ResetPassword
 *      notification carrying a hashed, expiring, single-use token. The
 *      answer is deliberately identical for registered and unknown
 *      addresses (no account enumeration), and the native broker throttle
 *      answers 429 for a second link to the same address within 60
 *      seconds. The route also runs under throttle:password_reset.
 *   2. POST /api/v1/auth/reset-password — consumes the token through the
 *      native broker: validates it, updates the password (hashed cast),
 *      rotates the remember token, dispatches PasswordReset, deletes the
 *      token row (single use), all inside a database transaction.
 *
 * Emails are intercepted with Notification::fake() (references/testing.md
 * §11): the suite never sends real mail. Login assertions reuse the real
 * authorization code + PKCE flow (references/testing.md §9).
 */
class PasswordResetTest extends TestCase
{
    /**
     * The registered user exercising the reset flow.
     */
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // The approved UserFactory hashes 'password' as the default password.
        $this->user = User::factory()->create();

        Notification::fake();
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    /**
     * A wrong-password login payload against the first-party client: the
     * cheapest real-flow proof of whether credentials authenticate.
     */
    protected function loginPayload(string $password): array
    {
        $verifier = rtrim(strtr(base64_encode(random_bytes(48)), '+/', '-_'), '=');

        return [
            'email' => $this->user->email,
            'password' => $password,
            'client_id' => Client::where('name', 'First Party PKCE Client')->sole()->id,
            'redirect_uri' => config('app.url'),
            'code_challenge' => rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '='),
            'code_challenge_method' => 'S256',
        ];
    }

    /**
     * Request a reset link and return the real token from the intercepted
     * notification.
     */
    protected function resetToken(): string
    {
        $this->postJson('/api/v1/auth/forgot-password', ['email' => $this->user->email])
            ->assertOk();

        return Notification::sent($this->user, ResetPassword::class)->first()->token;
    }

    /**
     * Call the reset endpoint with the given token and new password.
     */
    protected function resetWith(string $token, string $password = 'a-brand-new-password'): TestResponse
    {
        return $this->postJson('/api/v1/auth/reset-password', [
            'token' => $token,
            'email' => $this->user->email,
            'password' => $password,
            'password_confirmation' => $password,
        ]);
    }

    // ------------------------------------------------------------------
    // POST /api/v1/auth/forgot-password
    // ------------------------------------------------------------------

    public function test_forgot_password_sends_a_reset_link(): void
    {
        $this->postJson('/api/v1/auth/forgot-password', ['email' => $this->user->email])
            ->assertOk()
            ->assertJsonPath('status_code', 200)
            ->assertJsonPath('message', __('messages.password_reset_link_sent'));

        Notification::assertSentTo($this->user, ResetPassword::class);
    }

    public function test_forgot_password_answers_neutrally_for_unknown_emails(): void
    {
        $this->postJson('/api/v1/auth/forgot-password', ['email' => 'nobody@example.local'])
            ->assertOk()
            ->assertJsonPath('status_code', 200)
            ->assertJsonPath('message', __('messages.password_reset_link_sent'));

        // No notification for an unknown address — and, critically, the
        // same success answer the registered address receives, so the
        // endpoint cannot be used to enumerate accounts.
        Notification::assertNothingSent();
    }

    public function test_forgot_password_validates_the_email_field(): void
    {
        $this->postJson('/api/v1/auth/forgot-password', [])
            ->assertStatus(422)
            ->assertJsonPath('status_code', 422);

        $this->postJson('/api/v1/auth/forgot-password', ['email' => 'not-an-email'])
            ->assertStatus(422);
    }

    public function test_forgot_password_throttles_a_second_link_for_the_same_email(): void
    {
        // First link: sent (notification intercepted, the token row exists).
        $this->postJson('/api/v1/auth/forgot-password', ['email' => $this->user->email])
            ->assertOk();

        // Second link within the native broker throttle window
        // (auth.passwords.users.throttle, 60 seconds): 429 with the
        // Retry-After header carrying the native timeout.
        $this->postJson('/api/v1/auth/forgot-password', ['email' => $this->user->email])
            ->assertStatus(429)
            ->assertJsonPath('status_code', 429)
            ->assertHeader('Retry-After');
    }

    public function test_forgot_password_route_is_throttled_per_ip(): void
    {
        // throttle:password_reset (references/api-standards.md, Rate
        // Limiting): 5 attempts per minute per IP. Different unknown
        // addresses keep the per-email buckets fresh so the per-IP bucket
        // is what trips; every attempt still counts because the limiter
        // runs before the controller. Unknown (but valid) addresses get
        // the neutral 200 answer.
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/api/v1/auth/forgot-password', [
                'email' => "attacker{$attempt}@example.local",
            ])->assertOk();
        }

        $this->postJson('/api/v1/auth/forgot-password', ['email' => 'sixth@example.local'])
            ->assertStatus(429)
            ->assertHeader('Retry-After')
            ->assertHeader('X-RateLimit-Remaining', 0);
    }

    // ------------------------------------------------------------------
    // POST /api/v1/auth/reset-password
    // ------------------------------------------------------------------

    public function test_reset_password_updates_the_password(): void
    {
        $token = $this->resetToken();

        $this->resetWith($token)
            ->assertOk()
            ->assertJsonPath('status_code', 200)
            ->assertJsonPath('message', __('messages.password_reset_successfully'));

        // The database actually changed: the new password matches, the old
        // one does not, and the remember token was rotated (references/
        // testing.md §5.1, database state assertions).
        $fresh = $this->user->fresh();

        $this->assertTrue(Hash::check('a-brand-new-password', $fresh->password));
        $this->assertFalse(Hash::check('password', $fresh->password));
        $this->assertNotSame($this->user->remember_token, $fresh->remember_token);
    }

    public function test_reset_password_token_is_single_use(): void
    {
        $token = $this->resetToken();

        $this->resetWith($token)->assertOk();

        // The broker deletes the token row on success: replaying the same
        // token answers the invalid-token validation failure, and the row
        // stays deleted.
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $this->user->email]);

        $this->resetWith($token, 'another-new-password')
            ->assertStatus(422)
            ->assertJsonPath('status_code', 422);

        // The replay changed nothing.
        $this->assertTrue(Hash::check('a-brand-new-password', $this->user->fresh()->password));
    }

    public function test_reset_password_rejects_an_invalid_token(): void
    {
        $this->resetWith('not-a-real-token')
            ->assertStatus(422)
            ->assertJsonPath('status_code', 422)
            ->assertJsonPath('message', __('messages.validation_failed'));

        // Nothing changed.
        $this->assertTrue(Hash::check('password', $this->user->fresh()->password));
    }

    public function test_reset_password_rejects_an_unknown_email(): void
    {
        $token = $this->resetToken();

        $this->postJson('/api/v1/auth/reset-password', [
            'token' => $token,
            'email' => 'nobody@example.local',
            'password' => 'a-brand-new-password',
            'password_confirmation' => 'a-brand-new-password',
        ])->assertStatus(422);
    }

    public function test_reset_password_enforces_the_password_rules(): void
    {
        $token = $this->resetToken();

        // Too short (min:8).
        $this->resetWith($token, 'short')->assertStatus(422);

        // Unconfirmed (confirmation missing).
        $this->postJson('/api/v1/auth/reset-password', [
            'token' => $token,
            'email' => $this->user->email,
            'password' => 'a-brand-new-password',
        ])->assertStatus(422);

        // The token was never consumed by the failed attempts.
        $this->resetWith($token)->assertOk();
    }

    public function test_login_works_with_the_reset_password(): void
    {
        $token = $this->resetToken();
        $this->resetWith($token)->assertOk();

        // The full token lifecycle accepts the new credentials (the
        // lifecycle itself is specified by AuthTest).
        $this->postJson('/api/v1/auth/login', $this->loginPayload('a-brand-new-password'))
            ->assertOk()
            ->assertJsonPath('status_code', 200);

        $this->assertNotEmpty(
            $this->postJson('/api/v1/auth/login', $this->loginPayload('a-brand-new-password'))
                ->assertOk()
                ->json('data.code'),
            'Login with the reset password must issue a fresh authorization code.'
        );

        // The old password no longer authenticates.
        $this->postJson('/api/v1/auth/login', $this->loginPayload('password'))
            ->assertStatus(422);
    }
}
