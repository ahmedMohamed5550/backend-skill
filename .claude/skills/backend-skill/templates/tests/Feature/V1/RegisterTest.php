<?php

namespace Tests\Feature\V1;

use App\Models\V1\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Laravel\Passport\Client;
use Tests\TestCase;

/**
 * Registration spec (references/authentication.md, Registration).
 *
 * Flow under test:
 *
 *   POST /api/v1/auth/register (public, throttle:auth) — RegisterRequest
 *   validates name/email/password, AuthService::register() creates the user
 *   inside a database transaction and automatically mails the framework's
 *   native VerifyEmail notification (MustVerifyEmail machinery, references/
 *   authentication.md, Email Verification) after the transaction commits.
 *   The endpoint answers 201 with the new user's public profile and issues
 *   no tokens — the user logs in afterwards through the unchanged PKCE flow.
 *
 * Emails are intercepted with Notification::fake() (references/testing.md
 * §11): the suite never sends real mail.
 */
class RegisterTest extends TestCase
{
    /**
     * A valid registration payload; single fields are overridden per test.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function registerPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Jane Doe',
            'email' => 'jane@example.local',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
        ], $overrides);
    }

    public function test_registration_creates_the_user_and_sends_the_verification_email(): void
    {
        Notification::fake();

        $response = $this->postJson('/api/v1/auth/register', $this->registerPayload())
            ->assertStatus(201)
            ->assertJsonPath('status_code', 201)
            ->assertJsonPath('message', __('messages.registered'))
            ->assertJsonPath('data.name', 'Jane Doe')
            ->assertJsonPath('data.email', 'jane@example.local')
            ->assertJsonPath('data.email_verified_at', null);

        // The database actually changed (references/testing.md §5.1): the
        // user exists with a uuid (the model's creating hook), a hashed
        // password (the stored value is never the plain password), and no
        // verification stamp yet.
        $user = User::where('email', 'jane@example.local')->sole();

        $this->assertNotNull($user->uuid);
        $this->assertTrue(Hash::check('secret-password', $user->password));
        $this->assertNull($user->email_verified_at);

        // The response payload carries the public identifier, never the
        // database id.
        $this->assertSame($user->uuid, $response->json('data.uuid'));

        // The automatic verification email is the framework's native
        // VerifyEmail notification.
        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_the_emailed_verification_link_addresses_the_user_by_uuid(): void
    {
        Notification::fake();

        $this->postJson('/api/v1/auth/register', $this->registerPayload())->assertStatus(201);

        $user = User::where('email', 'jane@example.local')->sole();

        Notification::assertSentTo(
            $user,
            VerifyEmail::class,
            function (VerifyEmail $notification, array $channels) use ($user): bool {
                $actionUrl = $notification->toMail($user)->actionUrl;

                // The link's user parameter is the uuid — the public API
                // identifier — never the database id (references/authentication.md,
                // Email Verification).
                $this->assertStringContainsString("/email/verify/{$user->uuid}/", $actionUrl);
                $this->assertStringNotContainsString('/email/verify/'.((string) $user->id).'/', $actionUrl);

                return true;
            },
        );
    }

    public function test_a_registered_user_can_log_in_before_verifying_the_email(): void
    {
        Notification::fake();

        $this->postJson('/api/v1/auth/register', $this->registerPayload())->assertStatus(201);

        // Registration issues no tokens; the user logs in through the
        // unchanged authorization code + PKCE flow. Email verification does
        // not gate login.
        $verifier = rtrim(strtr(base64_encode(random_bytes(48)), '+/', '-_'), '=');

        $this->postJson('/api/v1/auth/login', [
            'email' => 'jane@example.local',
            'password' => 'secret-password',
            'client_id' => Client::where('name', 'First Party PKCE Client')->sole()->id,
            'redirect_uri' => config('app.url'),
            'code_challenge' => rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '='),
            'code_challenge_method' => 'S256',
        ])->assertOk();
    }

    public function test_registration_rejects_a_duplicate_email(): void
    {
        Notification::fake();

        User::factory()->create(['email' => 'taken@example.local']);

        $this->postJson('/api/v1/auth/register', $this->registerPayload(['email' => 'taken@example.local']))
            ->assertStatus(422)
            ->assertJsonPath('status_code', 422);

        // No user was created and no verification email went out for the
        // rejected registration.
        $this->assertSame(1, User::where('email', 'taken@example.local')->count());
        Notification::assertNothingSent();
    }

    public function test_registration_rejects_an_invalid_email_address(): void
    {
        Notification::fake();

        $this->postJson('/api/v1/auth/register', $this->registerPayload(['email' => 'not-an-email']))
            ->assertStatus(422);

        Notification::assertNothingSent();
    }

    public function test_registration_rejects_a_short_password(): void
    {
        Notification::fake();

        $this->postJson('/api/v1/auth/register', $this->registerPayload([
            'password' => 'short',
            'password_confirmation' => 'short',
        ]))->assertStatus(422);

        Notification::assertNothingSent();
    }

    public function test_registration_rejects_an_unconfirmed_password(): void
    {
        Notification::fake();

        $this->postJson('/api/v1/auth/register', $this->registerPayload([
            'password_confirmation' => 'different-password',
        ]))->assertStatus(422);

        Notification::assertNothingSent();
    }

    public function test_registration_requires_the_name_field(): void
    {
        $this->postJson('/api/v1/auth/register', $this->registerPayload(['name' => '']))
            ->assertStatus(422);
    }
}
