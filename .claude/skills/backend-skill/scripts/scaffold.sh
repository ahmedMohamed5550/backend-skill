#!/usr/bin/env bash
set -euo pipefail

# Backend Project Scaffold Script
#
# Creates a standardized Laravel backend project following SKILL.md section 2.
# The command order encodes the verified Laravel 13 / Passport 13 / Spatie v6
# setup sequence documented in references/authentication.md, including:
#   - Laravel 13 skeleton has no app/Http/Middleware directory (created here).
#   - Passport 13 publishes migrations instead of auto-loading them.
#   - The telescope publish can re-copy Passport oauth migrations (deduped here).
#   - passport:install is never used (it re-runs migrations and fails on
#     existing oauth tables); passport:keys + the PassportClientSeeder
#     template (registered in DatabaseSeeder) provide keys and the personal
#     access client instead.
#   - Authentication defaults come from templates/config/auth.php: the
#     default guard is `api` and the `api` guard uses the `passport` driver
#     with the `users` provider (references/authentication.md).
#   - Authorization config comes from templates/app/Models/V1/{Role,
#     Permission}.php plus rewrite-v1-imports.php pointing
#     config/permission.php models.role / models.permission at
#     App\Models\V1\Role / App\Models\V1\Permission
#     (references/architecture.md, Package Models Requiring Standard Columns).
#   - rewrite-v1-imports.php also prepares the skeleton DatabaseSeeder for
#     the lifecycle: it repoints the User import at App\Models\V1\User,
#     removes WithoutModelEvents (it suppresses the V1 User's creating hook,
#     so seeded users lose their required uuid), and registers
#     PassportClientSeeder — the single authoritative creator of the OAuth
#     clients that the token lifecycle and its AuthTest rely on
#     (references/authentication.md, Token Lifecycle).
#   - The token lifecycle (references/authentication.md, Token Lifecycle)
#     ships in the scaffold: templates/config/oauth.php plus the
#     AppServiceProvider template register the token TTLs, the
#     PassportClientSeeder seeds the public First Party PKCE Client, and the
#     Auth module templates (routes/v1/auth.php, AuthController,
#     AuthService, LoginRequest) implement the authorization code + PKCE
#     login whose code exchange and refresh run on the /oauth/token
#     endpoint the AppServiceProvider template registers itself (Passport::
#     ignoreRoutes() disables Passport's other OAuth routes, which must
#     answer 404). tests/Feature/V1/AuthTest.php is the lifecycle
#     feature test every generated project ships with.
#   - Rate limiting (references/api-standards.md, Rate Limiting) ships in
#     the scaffold: bootstrap/app.php enables the api baseline via
#     throttleApi(), the AppServiceProvider template defines the api, auth,
#     password_reset, and email_resend limiters, the login route carries
#     throttle:auth, the password reset routes carry throttle:password_reset
#     (the reserved config keys are attached now that the feature ships),
#     the resend route carries throttle:email_resend, the throttled
#     /oauth/token registration keeps Passport's own throttle middleware,
#     and config/rate_limiting.php holds the approved limits (with inert
#     reserved keys for the future OTP limiters). The AuthTest template
#     asserts the throttling behavior.
#   - Password reset and email verification (references/authentication.md,
#     Password Reset and Email Verification) ship in the scaffold as
#     default Auth module endpoints: the native Password broker (forgot-
#     password, reset-password + the two Auth requests), the native
#     MustVerifyEmail flow (the User template implements the contract, the
#     signed verification.verify route addressing the user by uuid through
#     the VerifyEmail::createUrlUsing() hook in the AppServiceProvider
#     template, resend behind auth:api), the ResetPassword::createUrlUsing
#     frontend link, the InvalidSignatureException rendering in
#     bootstrap/app.php, and the PasswordResetTest and
#     EmailVerificationTest feature specs.
#   - Registration (references/authentication.md, Registration) ships in the
#     scaffold as a default Auth module endpoint: POST /api/v1/auth/register
#     (public, throttle:auth) validates through the RegisterRequest
#     template, creates the user inside a database transaction, and
#     automatically mails the native VerifyEmail notification; the
#     RegisterTest feature spec asserts the flow.
#
# Usage:
#   bash scaffold.sh <project-folder-name> [database-name]
#
#   <project-folder-name>  lowercase kebab-case project folder name.
#   [database-name]        optional; defaults to the project name converted to
#                          lowercase snake_case.
#
# Workspace paths come from scripts/config.sh. MySQL credentials come from the
# developer's local.config.json at the skill root (template:
# local.config.example.json), loaded and validated through
# scripts/local-config.sh before any work starts: a missing or invalid
# configuration fails the scaffold immediately with setup instructions, and
# credentials are never hardcoded or defaulted (SKILL.md, Default Database
# Configuration). The loaded values are written into the generated project's
# .env and used for database creation; migrations and seeders then run against
# the project .env. Run this script in the background: Composer install.

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=config.sh
source "$SCRIPT_DIR/config.sh"
# shellcheck source=local-config.sh
source "$SCRIPT_DIR/local-config.sh"

TEMPLATES_DIR="$SKILL_DIR/templates"

PROJECT_NAME="${1:?Usage: scaffold.sh <project-folder-name> [database-name]}"
DB_NAME="${2:-$(echo "$PROJECT_NAME" | tr '-' '_')}"
PROJECT_PATH="$PROJECTS_DIR/$PROJECT_NAME"

command -v composer >/dev/null 2>&1 || { echo "ERROR: composer not found on PATH." >&2; exit 1; }
command -v php >/dev/null 2>&1 || { echo "ERROR: php not found on PATH." >&2; exit 1; }
command -v mysql >/dev/null 2>&1 || { echo "ERROR: mysql not found on PATH." >&2; exit 1; }

# Local developer database configuration (SKILL.md, Default Database
# Configuration): load and validate before any work starts. A missing or
# invalid local.config.json exits with setup instructions instead of guessing
# credentials. MYSQL_PWD carries the password to the mysql client (no
# process-list exposure, no shell-escaping issues); it is only set when
# non-empty so an empty password is passed as "no password".
load_local_db_config
if [ -n "$LOCAL_DB_PASSWORD" ]; then
    export MYSQL_PWD="$LOCAL_DB_PASSWORD"
fi
MYSQL_CLIENT=(mysql -h "$LOCAL_DB_HOST" -P "$LOCAL_DB_PORT" -u "$LOCAL_DB_USERNAME")

# Preflight: fail in seconds, before the 15+ minute Composer install.
"${MYSQL_CLIENT[@]}" -e "SELECT 1;" >/dev/null 2>&1 || {
    echo "ERROR: MySQL is not reachable at ${LOCAL_DB_USERNAME}@${LOCAL_DB_HOST}:${LOCAL_DB_PORT} with the credentials from local.config.json. Start MySQL/Laragon first and verify the credentials against local.config.json (template: local.config.example.json)." >&2
    exit 1
}
EXISTING_TABLES="$("${MYSQL_CLIENT[@]}" -N -B -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='$DB_NAME';")"
if [ "${EXISTING_TABLES:-0}" -gt 0 ]; then
    echo "ERROR: database '$DB_NAME' already exists with $EXISTING_TABLES tables." >&2
    echo "Re-run with an explicit database name: bash scaffold.sh $PROJECT_NAME <database-name>" >&2
    exit 1
fi

if [ -e "$PROJECT_PATH" ]; then
    echo "ERROR: $PROJECT_PATH already exists." >&2
    exit 1
fi

echo "==> [1/9] Creating Laravel skeleton (dependencies are NOT installed yet)"
composer create-project laravel/laravel "$PROJECT_PATH" --no-install --no-scripts

echo "==> [2/9] Applying approved templates"
# Laravel 13's skeleton does not ship these directories.
mkdir -p \
    "$PROJECT_PATH/app/Helpers" \
    "$PROJECT_PATH/app/Traits" \
    "$PROJECT_PATH/app/Http/Middleware" \
    "$PROJECT_PATH/app/Http/Controllers" \
    "$PROJECT_PATH/app/Http/Controllers/V1" \
    "$PROJECT_PATH/app/Models/V1" \
    "$PROJECT_PATH/app/Services/V1" \
    "$PROJECT_PATH/database/factories/V1" \
    "$PROJECT_PATH/tests" \
    "$PROJECT_PATH/tests/Feature/V1" \
    "$PROJECT_PATH/lang/en" \
    "$PROJECT_PATH/lang/ar" \
    "$PROJECT_PATH/routes/v1"
cp "$TEMPLATES_DIR/composer.json"                       "$PROJECT_PATH/composer.json"
cp "$TEMPLATES_DIR/bootstrap/app.php"                   "$PROJECT_PATH/bootstrap/app.php"
cp "$TEMPLATES_DIR/bootstrap/providers.php"             "$PROJECT_PATH/bootstrap/providers.php"
cp "$TEMPLATES_DIR/config/app.php"                      "$PROJECT_PATH/config/app.php"
cp "$TEMPLATES_DIR/config/auth.php"                     "$PROJECT_PATH/config/auth.php"
cp "$TEMPLATES_DIR/config/oauth.php"                    "$PROJECT_PATH/config/oauth.php"
cp "$TEMPLATES_DIR/config/rate_limiting.php"            "$PROJECT_PATH/config/rate_limiting.php"
cp "$TEMPLATES_DIR/app/Providers/AppServiceProvider.php" "$PROJECT_PATH/app/Providers/AppServiceProvider.php"
cp "$TEMPLATES_DIR/app/Helpers/Helpers.php"             "$PROJECT_PATH/app/Helpers/Helpers.php"
cp "$TEMPLATES_DIR/app/Traits/ApiResponseTrait.php"     "$PROJECT_PATH/app/Traits/ApiResponseTrait.php"
cp "$TEMPLATES_DIR/app/Http/Middleware/SetLocale.php"   "$PROJECT_PATH/app/Http/Middleware/SetLocale.php"
cp "$TEMPLATES_DIR/app/Http/Controllers/Controller.php" "$PROJECT_PATH/app/Http/Controllers/Controller.php"
cp "$TEMPLATES_DIR/lang/en/messages.php"                "$PROJECT_PATH/lang/en/messages.php"
cp "$TEMPLATES_DIR/lang/ar/messages.php"                "$PROJECT_PATH/lang/ar/messages.php"
cp "$TEMPLATES_DIR/routes/api1.php"                     "$PROJECT_PATH/routes/api1.php"
cp "$TEMPLATES_DIR/routes/documentation.php"            "$PROJECT_PATH/routes/v1/documentation.php"
cp "$TEMPLATES_DIR/routes/v1/auth.php"                  "$PROJECT_PATH/routes/v1/auth.php"
cp "$TEMPLATES_DIR/app/Http/Controllers/V1/DocumentationController.php" "$PROJECT_PATH/app/Http/Controllers/V1/DocumentationController.php"
cp "$TEMPLATES_DIR/app/Http/Controllers/V1/AuthController.php" "$PROJECT_PATH/app/Http/Controllers/V1/AuthController.php"
cp "$TEMPLATES_DIR/app/Services/V1/AuthService.php"     "$PROJECT_PATH/app/Services/V1/AuthService.php"
mkdir -p "$PROJECT_PATH/app/Http/Requests/V1/Auth"
cp "$TEMPLATES_DIR/app/Http/Requests/V1/Auth/LoginRequest.php" "$PROJECT_PATH/app/Http/Requests/V1/Auth/LoginRequest.php"
cp "$TEMPLATES_DIR/app/Http/Requests/V1/Auth/RegisterRequest.php" "$PROJECT_PATH/app/Http/Requests/V1/Auth/RegisterRequest.php"
cp "$TEMPLATES_DIR/app/Http/Requests/V1/Auth/ForgotPasswordRequest.php" "$PROJECT_PATH/app/Http/Requests/V1/Auth/ForgotPasswordRequest.php"
cp "$TEMPLATES_DIR/app/Http/Requests/V1/Auth/ResetPasswordRequest.php" "$PROJECT_PATH/app/Http/Requests/V1/Auth/ResetPasswordRequest.php"
cp "$TEMPLATES_DIR/tests/TestCase.php"                  "$PROJECT_PATH/tests/TestCase.php"
cp "$TEMPLATES_DIR/tests/Feature/V1/AuthTest.php"       "$PROJECT_PATH/tests/Feature/V1/AuthTest.php"
cp "$TEMPLATES_DIR/tests/Feature/V1/RegisterTest.php"   "$PROJECT_PATH/tests/Feature/V1/RegisterTest.php"
cp "$TEMPLATES_DIR/tests/Feature/V1/PasswordResetTest.php" "$PROJECT_PATH/tests/Feature/V1/PasswordResetTest.php"
cp "$TEMPLATES_DIR/tests/Feature/V1/EmailVerificationTest.php" "$PROJECT_PATH/tests/Feature/V1/EmailVerificationTest.php"
cp "$TEMPLATES_DIR/tests/Feature/V1/DocumentationTest.php" "$PROJECT_PATH/tests/Feature/V1/DocumentationTest.php"
cp "$TEMPLATES_DIR/database/factories/V1/UserFactory.php" "$PROJECT_PATH/database/factories/V1/UserFactory.php"
cp "$TEMPLATES_DIR/database/seeders/PassportClientSeeder.php" "$PROJECT_PATH/database/seeders/PassportClientSeeder.php"
cp "$TEMPLATES_DIR/README.md"                           "$PROJECT_PATH/README.md"
cp "$PROJECT_PATH/.env.example" "$PROJECT_PATH/.env"

# Single User model standard: App\Models\V1\User is the only User model
# (see references/models.md and references/architecture.md). Remove the
# Laravel skeleton's default User model and its default UserFactory, and
# install the approved V1 model templates. config/auth.php comes from the
# approved template (api default guard, passport api guard, V1 User import).
rm -f "$PROJECT_PATH/app/Models/User.php"
rm -f "$PROJECT_PATH/database/factories/UserFactory.php"
cp "$TEMPLATES_DIR/app/Models/V1/User.php" "$PROJECT_PATH/app/Models/V1/User.php"
cp "$TEMPLATES_DIR/app/Models/V1/Role.php" "$PROJECT_PATH/app/Models/V1/Role.php"
cp "$TEMPLATES_DIR/app/Models/V1/Permission.php" "$PROJECT_PATH/app/Models/V1/Permission.php"
# Repoint the skeleton's DatabaseSeeder import at the V1 User model, and (once
# Spatie's config is published) its permission config at the V1 Role and
# Permission models. Idempotent; verifies and fails loudly on any mismatch.
# (Done in PHP: the backslash-rich sed equivalents are shell-escape fragile.)
php "$SKILL_DIR/scripts/rewrite-v1-imports.php" "$PROJECT_PATH"
# Fail loudly instead of scaffolding a project whose configuration deviates
# from the approved templates (guards against template or skeleton changes).
grep -q '^use App.Models.V1.User;' "$PROJECT_PATH/config/auth.php" || {
    echo "ERROR: config/auth.php users provider does not reference App\\Models\\V1\\User." >&2
    exit 1
}
grep -qF "env('AUTH_GUARD', 'api')" "$PROJECT_PATH/config/auth.php" || {
    echo "ERROR: config/auth.php default guard is not 'api' (references/authentication.md)." >&2
    exit 1
}
grep -qF "'driver' => 'passport'" "$PROJECT_PATH/config/auth.php" || {
    echo "ERROR: config/auth.php has no 'passport' driver api guard (references/authentication.md)." >&2
    exit 1
}
# Token lifecycle checks (references/authentication.md, Token Lifecycle).
grep -qF "Passport::tokensExpireIn" "$PROJECT_PATH/app/Providers/AppServiceProvider.php" \
    && grep -qF "Passport::refreshTokensExpireIn" "$PROJECT_PATH/app/Providers/AppServiceProvider.php" || {
    echo "ERROR: AppServiceProvider.php does not register the Passport token TTLs." >&2
    exit 1
}
# Passport route hardening checks (references/authentication.md, Passport
# route hardening): automatic routes disabled, only POST /oauth/token
# registered with Passport's own throttle middleware.
grep -qF "Passport::ignoreRoutes" "$PROJECT_PATH/app/Providers/AppServiceProvider.php" \
    && grep -qF "AccessTokenController::class" "$PROJECT_PATH/app/Providers/AppServiceProvider.php" \
    && grep -qFe "->middleware('throttle')" "$PROJECT_PATH/app/Providers/AppServiceProvider.php" || {
    echo "ERROR: AppServiceProvider.php does not implement the Passport route hardening (ignoreRoutes + the throttled /oauth/token registration)." >&2
    exit 1
}
# Rate limiting checks (references/api-standards.md, Rate Limiting): the api
# baseline on the middleware group, the api/auth limiter definitions, the
# login middleware, and the approved config keys.
grep -qF "throttleApi" "$PROJECT_PATH/bootstrap/app.php" \
    && grep -qF "RateLimiter::for('api'" "$PROJECT_PATH/app/Providers/AppServiceProvider.php" \
    && grep -qF "RateLimiter::for('auth'" "$PROJECT_PATH/app/Providers/AppServiceProvider.php" \
    && grep -qF "throttle:auth" "$PROJECT_PATH/routes/v1/auth.php" \
    && grep -qF "'api_per_minute'" "$PROJECT_PATH/config/rate_limiting.php" \
    && grep -qF "'auth_per_minute'" "$PROJECT_PATH/config/rate_limiting.php" \
    && grep -qF "'auth_per_hour'" "$PROJECT_PATH/config/rate_limiting.php" || {
    echo "ERROR: the approved rate limiters are not configured (bootstrap/app.php throttleApi, AppServiceProvider api/auth limiters, throttle:auth on login, config/rate_limiting.php keys)." >&2
    exit 1
}
# Password reset limiter checks (references/authentication.md, Password
# Reset): the password_reset limiter definition, its throttle middleware on
# the reset routes, and the (formerly reserved) config keys now attached.
grep -qF "RateLimiter::for('password_reset'" "$PROJECT_PATH/app/Providers/AppServiceProvider.php" \
    && grep -qF "throttle:password_reset" "$PROJECT_PATH/routes/v1/auth.php" \
    && grep -qF "'password_reset_per_minute'" "$PROJECT_PATH/config/rate_limiting.php" \
    && grep -qF "'password_reset_per_hour'" "$PROJECT_PATH/config/rate_limiting.php" || {
    echo "ERROR: the password reset rate limiter is not configured (AppServiceProvider password_reset limiter, throttle:password_reset on the reset routes, config/rate_limiting.php keys)." >&2
    exit 1
}
# Email verification limiter check (references/authentication.md, Email
# Verification): the email_resend limiter definition and its config keys.
grep -qF "RateLimiter::for('email_resend'" "$PROJECT_PATH/app/Providers/AppServiceProvider.php" \
    && grep -qF "throttle:email_resend" "$PROJECT_PATH/routes/v1/auth.php" \
    && grep -qF "'email_resend_per_minute'" "$PROJECT_PATH/config/rate_limiting.php" \
    && grep -qF "'email_resend_per_hour'" "$PROJECT_PATH/config/rate_limiting.php" || {
    echo "ERROR: the email resend rate limiter is not configured (AppServiceProvider email_resend limiter, throttle:email_resend on the resend route, config/rate_limiting.php keys)." >&2
    exit 1
}
# Password reset (references/authentication.md, Password Reset): the native
# broker is the only reset implementation, the reset link points at the
# frontend, and the reset request templates are installed.
grep -qF "Password::sendResetLink" "$PROJECT_PATH/app/Services/V1/AuthService.php" \
    && grep -qF "Password::reset" "$PROJECT_PATH/app/Services/V1/AuthService.php" \
    && grep -qF "ResetPassword::createUrlUsing" "$PROJECT_PATH/app/Providers/AppServiceProvider.php" \
    && grep -qF "frontend_url" "$PROJECT_PATH/config/app.php" \
    && [ -s "$PROJECT_PATH/app/Http/Requests/V1/Auth/ForgotPasswordRequest.php" ] \
    && [ -s "$PROJECT_PATH/app/Http/Requests/V1/Auth/ResetPasswordRequest.php" ] || {
    echo "ERROR: the native password reset feature is not installed (AuthService Password broker calls, ResetPassword::createUrlUsing, config/app.php frontend_url, ForgotPasswordRequest, ResetPasswordRequest)." >&2
    exit 1
}
# Email verification (references/authentication.md, Email Verification): the
# User model implements the native contract, the signed verification.verify
# route addresses the user by uuid through the VerifyEmail::createUrlUsing()
# hook in the AppServiceProvider, and bootstrap/app.php renders signed-link
# rejections.
grep -qF "implements MustVerifyEmail" "$PROJECT_PATH/app/Models/V1/User.php" \
    && grep -qF "verification.verify" "$PROJECT_PATH/routes/v1/auth.php" \
    && grep -qF "/email/verify/{uuid}/{hash}" "$PROJECT_PATH/routes/v1/auth.php" \
    && grep -qF "middleware('signed')" "$PROJECT_PATH/routes/v1/auth.php" \
    && grep -qF "VerifyEmail::createUrlUsing" "$PROJECT_PATH/app/Providers/AppServiceProvider.php" \
    && grep -qF "InvalidSignatureException" "$PROJECT_PATH/bootstrap/app.php" \
    && grep -qF "sendEmailVerificationNotification" "$PROJECT_PATH/app/Services/V1/AuthService.php" \
    && grep -qF "markEmailAsVerified" "$PROJECT_PATH/app/Services/V1/AuthService.php" || {
    echo "ERROR: the native email verification feature is not installed (User implements MustVerifyEmail, signed verification.verify route with the uuid parameter, VerifyEmail::createUrlUsing hook, InvalidSignatureException rendering, AuthService verification mechanics)." >&2
    exit 1
}
# Registration (references/authentication.md, Registration): the default
# register endpoint ships with the scaffold — the public register route with
# the auth limiter, the register action wired to the service, and the
# RegisterRequest template.
grep -qF "Route::post('/register', 'register')" "$PROJECT_PATH/routes/v1/auth.php" \
    && grep -qF "public function register(RegisterRequest" "$PROJECT_PATH/app/Http/Controllers/V1/AuthController.php" \
    && grep -qF "public function register(array" "$PROJECT_PATH/app/Services/V1/AuthService.php" \
    && grep -qF "sendEmailVerificationNotification" "$PROJECT_PATH/app/Services/V1/AuthService.php" \
    && [ -s "$PROJECT_PATH/app/Http/Requests/V1/Auth/RegisterRequest.php" ] \
    && [ -s "$PROJECT_PATH/tests/Feature/V1/RegisterTest.php" ] || {
    echo "ERROR: the default register endpoint is not installed (register route with throttle:auth, AuthController register action, AuthService register with the automatic verification email, RegisterRequest, RegisterTest)." >&2
    exit 1
}
# Fortify is not part of the approved architecture: Laravel's native
# machinery plus Passport own every auth flow (references/authentication.md,
# Email Verification), and Fortify's emailVerification feature would
# register a second session-auth route under the same `verification.verify`
# name the framework's VerifyEmail notification resolves. The dependency
# and its config must stay absent from the generated project.
test ! -f "$PROJECT_PATH/config/fortify.php" \
    && ! grep -qiF "laravel/fortify" "$PROJECT_PATH/composer.json" || {
    echo "ERROR: Fortify is not part of the approved architecture; remove laravel/fortify from composer.json and delete config/fortify.php — its emailVerification feature would hijack the verification.verify route name." >&2
    exit 1
}
# Password reset and email verification message keys (references/
# localization.md): en and ar carry the localized flow messages.
for MESSAGE_KEY in registered password_reset_link_sent password_reset_successfully invalid_reset_token email_verification_sent email_already_verified invalid_verification_link email_verified_successfully; do
    grep -qF "'$MESSAGE_KEY'" "$PROJECT_PATH/lang/en/messages.php" \
        && grep -qF "'$MESSAGE_KEY'" "$PROJECT_PATH/lang/ar/messages.php" || {
        echo "ERROR: the '$MESSAGE_KEY' message key is missing from lang/en/messages.php or lang/ar/messages.php." >&2
        exit 1
    }
done
# Passport route hardening (references/authentication.md, Passport route
# hardening): automatic routes disabled, single manually registered endpoint.
grep -qF "Passport::ignoreRoutes" "$PROJECT_PATH/app/Providers/AppServiceProvider.php" \
    && grep -qF "AccessTokenController::class" "$PROJECT_PATH/app/Providers/AppServiceProvider.php" || {
    echo "ERROR: AppServiceProvider.php does not disable Passport's routes and register POST /oauth/token." >&2
    exit 1
}
grep -qF "'access_token_expires_in'" "$PROJECT_PATH/config/oauth.php" \
    && grep -qF "'refresh_token_expires_in'" "$PROJECT_PATH/config/oauth.php" || {
    echo "ERROR: config/oauth.php is missing the token lifetime keys." >&2
    exit 1
}
grep -qF "'authorization_code'" "$PROJECT_PATH/database/seeders/PassportClientSeeder.php" \
    && grep -qF "'refresh_token'" "$PROJECT_PATH/database/seeders/PassportClientSeeder.php" || {
    echo "ERROR: PassportClientSeeder.php does not seed the First Party PKCE Client grants." >&2
    exit 1
}
# Public authentication configuration (references/authentication.md, Token
# Lifecycle): the documentation endpoint resolves the seeded public First
# Party PKCE Client and serves its client_id, redirect_uri, and S256 method.
grep -qF "First Party PKCE Client" "$PROJECT_PATH/app/Http/Controllers/V1/DocumentationController.php" \
    && grep -qF "S256" "$PROJECT_PATH/app/Http/Controllers/V1/DocumentationController.php" || {
    echo "ERROR: DocumentationController.php does not expose the public authentication configuration (client_id, redirect_uri, S256) of the First Party PKCE Client." >&2
    exit 1
}
grep -q '^use App.Models.V1.User;' "$PROJECT_PATH/database/seeders/DatabaseSeeder.php" || {
    echo "ERROR: DatabaseSeeder.php does not reference App\\Models\\V1\\User." >&2
    exit 1
}
grep -rEn 'App.Models.User(;|::)' \
    "$PROJECT_PATH/app" "$PROJECT_PATH/config" "$PROJECT_PATH/database" \
    "$PROJECT_PATH/bootstrap" "$PROJECT_PATH/routes" "$PROJECT_PATH/tests" \
    >/dev/null 2>&1 && {
    echo "ERROR: a reference to the removed default App\\Models\\User remains after scaffold transformation." >&2
    exit 1
}

echo "==> [3/9] Configuring .env for MySQL"
# APP_NAME is a safe single-token value and stays in sed. The database block
# comes from the validated local developer configuration (local.config.json,
# SKILL.md, Default Database Configuration): write-env-db.php matches both
# uncommented (Laravel 13 skeleton ships DB_CONNECTION=sqlite live) and
# commented-out ("# DB_...") skeleton defaults, edits the first occurrence of
# every key in place (Laravel env loading keeps the FIRST occurrence), and
# handles password characters a sed replacement could not carry safely.
sed -i -E -e "s/^APP_NAME=.*/APP_NAME=\"$PROJECT_NAME\"/" "$PROJECT_PATH/.env"
DB_CONNECTION=mysql \
DB_DATABASE="$DB_NAME" \
DB_HOST="$LOCAL_DB_HOST" \
DB_PORT="$LOCAL_DB_PORT" \
DB_USERNAME="$LOCAL_DB_USERNAME" \
DB_PASSWORD="$LOCAL_DB_PASSWORD" \
    php "$SKILL_DIR/scripts/write-env-db.php" "$PROJECT_PATH"
# Keep the skill-level developer configuration out of the project's committed
# files even if it is ever copied into the project folder (SKILL.md, Default
# Database Configuration): the file lives in the skill workspace and is never
# copied by the scaffold; this line is the defensive net.
printf '\n# Local developer database configuration (never commit credentials)\nlocal.config.json\n' >> "$PROJECT_PATH/.gitignore"
APP_URL_VALUE="$(grep -E '^APP_URL=' "$PROJECT_PATH/.env" | head -n 1 | cut -d= -f2-)"
printf '\nL5_SWAGGER_CONST_HOST="%s"\nL5_SWAGGER_GENERATE_ALWAYS=true\n' "$APP_URL_VALUE" >> "$PROJECT_PATH/.env"
# Token lifetime defaults (config/oauth.php), visible and overridable per project.
printf '\nOAUTH_ACCESS_TOKEN_EXPIRES_IN=15\nOAUTH_REFRESH_TOKEN_EXPIRES_IN=30\n' >> "$PROJECT_PATH/.env"
# Rate limiting defaults (config/rate_limiting.php), visible and overridable per project
# (references/api-standards.md, Rate Limiting). The reserved otp_* keys stay
# config-only until the optional OTP feature is implemented.
printf '\nRATE_LIMIT_API_PER_MINUTE=60\nRATE_LIMIT_AUTH_PER_MINUTE=5\nRATE_LIMIT_AUTH_PER_HOUR=30\nRATE_LIMIT_PASSWORD_RESET_PER_MINUTE=5\nRATE_LIMIT_PASSWORD_RESET_PER_HOUR=30\nRATE_LIMIT_EMAIL_RESEND_PER_MINUTE=6\nRATE_LIMIT_EMAIL_RESEND_PER_HOUR=30\n' >> "$PROJECT_PATH/.env"
# Frontend base URL for the native password reset email link (references/
# authentication.md, Password Reset): defaults to the API URL until a
# separate frontend exists.
printf 'FRONTEND_URL=%s\n' "$APP_URL_VALUE" >> "$PROJECT_PATH/.env"
# Fill the README placeholders that come from the project/database names;
# {{PROJECT_DESCRIPTION}} is filled manually from the stated requirements.
sed -i "s/{{PROJECT_NAME}}/$PROJECT_NAME/g; s/{{DB_NAME}}/$DB_NAME/g" "$PROJECT_PATH/README.md"
# The AuthController template holds the global Swagger annotations, whose
# title carries the same {{PROJECT_NAME}} placeholder as the README.
sed -i "s/{{PROJECT_NAME}}/$PROJECT_NAME/g" "$PROJECT_PATH/app/Http/Controllers/V1/AuthController.php"

echo "==> [4/9] Installing Composer dependencies (the long step)"
echo "    NOTE: vendor/autoload.php does not exist until this step finishes."
echo "    Do NOT run artisan commands (e.g. 'php artisan serve') before this script prints 'Scaffold complete':"
echo "    artisan fails with 'Failed opening required vendor/autoload.php' while Composer is still installing."
(cd "$PROJECT_PATH" && composer install --no-interaction --no-progress)

echo "==> [5/9] Creating MySQL database if it does not exist"
"${MYSQL_CLIENT[@]}" -e "CREATE DATABASE IF NOT EXISTS \`$DB_NAME\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

echo "==> [6/9] Adding the uuid column to the default users migration"
USERS_MIGRATION="$PROJECT_PATH/database/migrations/0001_01_01_000000_create_users_table.php"
sed -i "0,/^\( *\)\\\$table->id();/s//\1\$table->id();\n\1\$table->uuid('uuid')->unique();/" "$USERS_MIGRATION"

echo "==> [7/9] Publishing package migrations and configs"
(cd "$PROJECT_PATH" && php artisan key:generate --no-interaction)
(cd "$PROJECT_PATH" && php artisan vendor:publish --tag=passport-migrations --no-interaction)
(cd "$PROJECT_PATH" && php artisan vendor:publish --tag=telescope-migrations --no-interaction)

# The telescope publish can re-copy Passport's oauth migrations.
# Keep only the earliest migration per oauth table.
(
    cd "$PROJECT_PATH/database/migrations"
    ls | grep '_create_oauth_' | sort | awk -F'_create_oauth_' 'seen[$2]++ {print}' \
        | while IFS= read -r duplicate; do
            rm -f "$duplicate"
            echo "    Removed duplicate oauth migration: $duplicate"
        done
)

(cd "$PROJECT_PATH" && php artisan vendor:publish --provider="Spatie\\Permission\\PermissionServiceProvider" --no-interaction)
# l5-swagger has no usable publish tag for its config; copy it from vendor.
cp "$PROJECT_PATH/vendor/darkaonline/l5-swagger/config/l5-swagger.php" "$PROJECT_PATH/config/l5-swagger.php"

# Skill standard: every table holding application records needs a uuid column.
# Insert it into Spatie's published migration; only the permissions and roles
# blocks use bigIncrements, so one sed patches both.
PERM_MIGRATION="$(ls "$PROJECT_PATH/database/migrations" | grep '_create_permission_tables' | head -n 1 || true)"
test -n "$PERM_MIGRATION" || { echo "ERROR: Spatie permission tables migration not found after vendor:publish." >&2; exit 1; }
sed -i "s/^\( *\)\\\$table->bigIncrements('id');\(.*\)$/\1\$table->bigIncrements('id');\2\n\1\$table->uuid('uuid')->unique();/" \
    "$PROJECT_PATH/database/migrations/$PERM_MIGRATION"

# Point Spatie's published config at the V1 Role and Permission models
# (references/architecture.md). The models themselves were installed from
# templates in step [2/9], so the classes exist before anything boots.
php "$SKILL_DIR/scripts/rewrite-v1-imports.php" "$PROJECT_PATH"
grep -q '^use App.Models.V1.Role;' "$PROJECT_PATH/config/permission.php" \
    && grep -q '^use App.Models.V1.Permission;' "$PROJECT_PATH/config/permission.php" || {
    echo "ERROR: config/permission.php does not reference the App\\Models\\V1\\Role and App\\Models\\V1\\Permission models." >&2
    exit 1
}
grep -qF "'role' => Role::class," "$PROJECT_PATH/config/permission.php" \
    && grep -qF "'permission' => Permission::class," "$PROJECT_PATH/config/permission.php" || {
    echo "ERROR: config/permission.php models.role / models.permission do not resolve to the imported V1 models." >&2
    exit 1
}

echo "==> [8/9] Running migrations and preparing Passport"
(cd "$PROJECT_PATH" && php artisan migrate --no-interaction --force)
(cd "$PROJECT_PATH" && php artisan passport:keys --no-interaction)
# No `passport:client` commands here: the PassportClientSeeder template
# (registered in DatabaseSeeder by the module seeder step) is the single
# authoritative creator of the personal access client AND the public First
# Party PKCE Client, in the main database and in the test database alike
# (references/authentication.md, Token Lifecycle).

echo "==> [9/9] Seeding (only once the Roles and Permissions Seeder is registered)"
if grep -q "RolesAndPermissionsSeeder" "$PROJECT_PATH/database/seeders/DatabaseSeeder.php"; then
    (cd "$PROJECT_PATH" && php artisan db:seed --no-interaction --force)
else
    echo "    Skipped: RolesAndPermissionsSeeder is not registered in DatabaseSeeder yet."
    echo "    Run 'php artisan db:seed' after implementing the module seeders."
fi

echo "==> Scaffold complete: $PROJECT_PATH (database: $DB_NAME)"
echo "    Completion gate: this project is BLOCKED until"
echo "    'bash scripts/completion-gate.sh $PROJECT_NAME' passes (SKILL.md, Project Completion Gate)."
echo "    A BLOCKED project must not be handed off to the Frontend Team or any other team."
