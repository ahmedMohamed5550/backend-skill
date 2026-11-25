<?php

namespace App\Services\V1;

use App\Models\V1\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Passport\Bridge\User as BridgeUser;
use Laravel\Passport\RefreshToken;
use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\Exception\OAuthServerException;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

/**
 * Auth module service.
 *
 * The token lifecycle runs on Passport's authorization server — no token is
 * generated or stored manually. Login verifies the credentials, then drives
 * the authorization code + PKCE flow of the seeded public first-party client
 * and returns the authorization code. The client exchanges that code (plus
 * the code_verifier) at Passport's own token endpoint, which issues the
 * access and refresh token pair; the same endpoint rotates the pair on every
 * refresh (references/authentication.md, Token Lifecycle).
 *
 * Password reset and email verification run on Laravel's own native
 * machinery — the Password broker (password_reset_tokens + the ResetPassword
 * notification) and the MustVerifyEmail contract with the VerifyEmail
 * notification's signed verification URL (references/authentication.md,
 * Password Reset and Email Verification). No token logic is reimplemented
 * here: the broker hashes and expires reset tokens, and the framework signs
 * and expires the verification link.
 */
class AuthService
{
    public function __construct(
        protected AuthorizationServer $authorizationServer,
    ) {
    }

    /**
     * Register a new user and automatically send the framework's
     * VerifyEmail notification (references/authentication.md, Registration
     * and Email Verification).
     *
     * The user is created inside a database transaction: the model's
     * 'hashed' cast hashes the plain password and the creating hook
     * generates the uuid. The native verification email goes out only
     * after the transaction committed — the VerifyEmail notification is
     * queued, so it must never fire for an uncommitted row. The signed
     * verification URL is built by the framework through the
     * VerifyEmail::createUrlUsing() hook registered in the
     * AppServiceProvider (uuid-based link) — no custom notification.
     *
     * Registration does not authenticate the caller and issues no tokens;
     * clients log in afterwards through the unchanged PKCE flow.
     */
    public function register(array $data): User
    {
        $user = DB::transaction(fn (): User => User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
        ]));

        $user->sendEmailVerificationNotification();

        return $user;
    }

    /**
     * Verify the credentials and issue an authorization code for the
     * first-party PKCE client. The code exchange happens at POST /oauth/token.
     */
    public function login(array $data): string
    {
        $user = User::where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => __('messages.invalid_credentials'),
            ]);
        }

        try {
            return $this->issueAuthorizationCode(
                $user,
                $data['client_id'],
                $data['redirect_uri'],
                $data['code_challenge'],
                $data['code_challenge_method'],
            );
        } catch (OAuthServerException) {
            // Unknown/inactive client, a client not authorized for the
            // authorization_code grant, or a redirect URI mismatch: the
            // expected business failure of the login request.
            throw ValidationException::withMessages([
                'client_id' => __('messages.invalid_client'),
            ]);
        }
    }

    /**
     * Revoke the current access token and its associated refresh token.
     */
    public function logout(User $user): void
    {
        $token = $user->token();

        if (! $token) {
            return;
        }

        DB::transaction(function () use ($token): void {
            $token->revoke();

            RefreshToken::where('access_token_id', $token->id)
                ->where('revoked', false)
                ->get()
                ->each
                ->revoke();
        });
    }

    /**
     * Send the password reset link through Laravel's native Password
     * broker (references/authentication.md, Password Reset).
     *
     * The broker stores a hashed, expiring token in password_reset_tokens
     * and mails the ResetPassword notification. A link is only sent when
     * the email belongs to a registered user; the caller answers neutrally
     * either way so the endpoint never reveals whether an account exists.
     *
     * The broker's own throttle (auth.passwords.users.throttle, default
     * 60 seconds per email) rejects repeated requests: reported as 429
     * with the native retry timeout so clients know when to try again.
     *
     * @throws TooManyRequestsHttpException when the native broker throttle
     *                                      trips (ResetThrottled status)
     */
    public function sendPasswordResetLink(array $data): void
    {
        $status = Password::sendResetLink($data);

        if ($status === Password::RESET_LINK_SENT || $status === Password::INVALID_USER) {
            // A link was sent, or the address is unknown: both answer the
            // same neutral success so the endpoint cannot be used to
            // enumerate registered accounts.
            return;
        }

        // The remaining native status is RESET_THROTTLED: this address
        // already received a link within the broker's throttle window.
        // The native throttle seconds come from auth.passwords.users.
        throw new TooManyRequestsHttpException(
            (int) config('auth.passwords.users.throttle', 60),
            __('messages.rate_limited'),
        );
    }

    /**
     * Reset the password with Laravel's native Password broker
     * (references/authentication.md, Password Reset).
     *
     * The broker validates the hashed token from the request against the
     * password_reset_tokens row, resolves the user, deletes the token, and
     * hands the plain new password to the closure. The closure updates the
     * password (the model's hashed cast hashes it on save), rotates the
     * remember token, and dispatches the native PasswordReset event. All
     * inside a database transaction.
     *
     * @throws ValidationException for an invalid/expired token or unknown
     *                            email (the native InvalidToken/InvalidUser
     *                            statuses — never a 500)
     */
    public function resetPassword(array $data): void
    {
        $status = Password::reset(
            $data,
            function (User $user, string $password): void {
                DB::transaction(function () use ($user, $password): void {
                    $user->forceFill([
                        // The model's 'hashed' cast hashes the plain value.
                        'password' => $password,
                        'remember_token' => Str::random(60),
                    ])->save();
                });

                event(new PasswordReset($user));
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            // InvalidToken (wrong, used, or expired token) or InvalidUser
            // (unknown address): the expected business failure of the
            // reset request.
            throw ValidationException::withMessages([
                'email' => __('messages.invalid_reset_token'),
            ]);
        }
    }

    /**
     * Send the framework's VerifyEmail notification to the authenticated
     * user (references/authentication.md, Email Verification).
     *
     * The notification builds the signed verification URL for the route
     * named verification.verify — Laravel's native mechanics, no custom
     * notification. Resending for an already verified account is a 422
     * business failure, not a silent success.
     *
     * @throws ValidationException when the account is already verified
     */
    public function resendVerificationEmail(User $user): void
    {
        if ($user->hasVerifiedEmail()) {
            throw ValidationException::withMessages([
                'email' => __('messages.email_already_verified'),
            ]);
        }

        $user->sendEmailVerificationNotification();
    }

    /**
     * Verify the email of the user addressed by a signed verification link
     * (references/authentication.md, Email Verification).
     *
     * The signed middleware has already proven the link's signature and
     * expiry before this runs. The user is resolved by uuid — the link's
     * user parameter is the public API identifier, never the database id.
     * The hash check mirrors the framework's own EmailVerificationRequest
     * validation: the link is only valid for the user whose email it was
     * generated from. markEmailAsVerified() stamps email_verified_at and
     * dispatches the native Verified event — inside a database transaction.
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException when no
     *             user exists for the link's uuid
     * @throws ValidationException when the hash does not match the user's
     *                            email (a link swapped between accounts)
     */
    public function verifyEmail(string $uuid, string $hash): void
    {
        $user = User::where('uuid', $uuid)->firstOrFail();

        if (! hash_equals(sha1($user->getEmailForVerification()), $hash)) {
            throw ValidationException::withMessages([
                'hash' => __('messages.invalid_verification_link'),
            ]);
        }

        if (! $user->hasVerifiedEmail()) {
            DB::transaction(fn () => $user->markEmailAsVerified());
        }
    }

    /**
     * Drive Passport's authorization server directly for a logged-in user
     * who has already approved the first-party client: build the
     * authorization request, approve it as the user, and return the issued
     * authorization code from the redirect location.
     *
     * @throws OAuthServerException when the client is unknown, inactive, not
     *                              authorized for the authorization_code
     *                              grant, or the redirect URI does not match
     */
    protected function issueAuthorizationCode(
        User $user,
        string $clientId,
        string $redirectUri,
        string $codeChallenge,
        string $codeChallengeMethod,
    ): string {
        $psr17 = new Psr17Factory();

        $psrRequest = (new ServerRequest('GET', $redirectUri))
            ->withQueryParams([
                'client_id' => $clientId,
                'redirect_uri' => $redirectUri,
                'response_type' => 'code',
                'code_challenge' => $codeChallenge,
                'code_challenge_method' => $codeChallengeMethod,
            ]);

        $authorizationRequest = $this->authorizationServer->validateAuthorizationRequest($psrRequest);
        $authorizationRequest->setUser(new BridgeUser((string) $user->getAuthIdentifier()));
        $authorizationRequest->setAuthorizationApproved(true);

        $psrResponse = $this->authorizationServer->completeAuthorizationRequest(
            $authorizationRequest,
            $psr17->createResponse(),
        );

        // league returns a 302 redirect carrying the code; the code is the
        // only part the JSON login response needs.
        parse_str((string) parse_url($psrResponse->getHeaderLine('Location'), PHP_URL_QUERY), $query);

        return $query['code'];
    }
}
