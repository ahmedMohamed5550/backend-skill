#!/usr/bin/env bash
set -uo pipefail

# Backend Project Completion Gate
#
# SKILL.md section "Project Completion Gate": a project is READY only when every
# gate criterion passes; otherwise it stays BLOCKED and must not be handed off
# to the Frontend Team or any other team. This script is the machine-checkable
# half of the gate; the judgment half (requirement coverage, documentation
# accuracy, authentication/integration correctness) is described in SKILL.md
# and must still be verified before reporting READY.
#
# Usage:
#   bash completion-gate.sh <project-folder-name>
#
#   <project-folder-name>  lowercase kebab-case project folder name inside the
#                          configured Projects Workspace (scripts/config.sh).
#
# Verdicts:
#   READY   every check passes (exit code 0).
#   BLOCKED at least one check failed (exit code 1). The failure list is
#           printed; fix the root causes and re-run the gate.
#   A missing project folder or unusable environment is an error (exit code 2)
#           and also means the project is not READY.
#
# Checks and the gate criteria they verify (SKILL.md, Gate Criteria):
#   CHECK 01 dependencies -> criterion 2 (Composer and dependencies installed)
#   CHECK 02 configuration -> criterion 3 (.env, MySQL connection, APP_KEY)
#   CHECK 03 database     -> criterion 3 (migrations fully executed)
#   CHECK 04 routes       -> criterion 4 (routes compile, api/v1 registered)
#   CHECK 05 swagger      -> criterion 5 (OpenAPI specification generates)
#   CHECK 06 docs files   -> criterion 5 (required documentation files
#                            exist; docs/API_DOCUMENTATION.md starts with
#                            the Public Authentication Configuration
#                            section whose client_id and redirect_uri
#                            match the seeded First Party PKCE Client in
#                            the database, plus S256 — never hardcoded)
#   CHECK 07 standards    -> criterion 1 (machine-checkable skill requirements:
#                            single V1 User model/factory, approved auth
#                            config (api guard / passport driver), approved
#                            permission config (V1 Spatie models), the token
#                            lifecycle files and OAuth client contract
#                            (references/authentication.md, Token Lifecycle),
#                            the default register endpoint with the automatic
#                            native verification email (references/
#                            authentication.md, Registration), the native
#                            password reset and email verification features
#                            with the uuid-based signed verification link
#                            (references/authentication.md, Password Reset
#                            and Email Verification), the public
#                            authentication configuration exposure
#                            on the documentation endpoint, the hardened
#                            Passport route table (only
#                            POST /oauth/token registered), the approved rate
#                            limiters (references/api-standards.md, Rate
#                            Limiting), route-level auth:api
#                            middleware placement, README without unresolved
#                            placeholders)
#   CHECK 08 tests        -> criterion 6 (complete required test suite passes)
#   CHECK 09 boot         -> criterion 7 (application boots and serves
#                            GET /api/v1/documentation successfully,
#                            beginning with the Public Authentication
#                            Configuration section)
#
# Notes:
#   - Run this script in the background: the full test suite (CHECK 08) can
#     take several minutes.
#   - Tests run through phpunit.xml, so they target the isolated *_test
#     database (references/testing.md section 4), never the main database.
#   - Override the smoke-test port with GATE_PORT=<port> when 8123 is busy.

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=config.sh
source "$SCRIPT_DIR/config.sh"

PROJECT_NAME="${1:?Usage: completion-gate.sh <project-folder-name>}"
PROJECT_PATH="$PROJECTS_DIR/$PROJECT_NAME"
GATE_PORT="${GATE_PORT:-8123}"
SMOKE_URL="http://127.0.0.1:${GATE_PORT}/api/v1/documentation"

if [ ! -d "$PROJECT_PATH" ]; then
    echo "ERROR: project folder not found: $PROJECT_PATH" >&2
    echo "The gate cannot run without the project; the project is not READY." >&2
    exit 2
fi
if ! command -v php >/dev/null 2>&1; then
    echo "ERROR: php not found on PATH." >&2
    exit 2
fi

echo "============================================================"
echo " Backend Project Completion Gate: $PROJECT_NAME"
echo "============================================================"

FAILURES=()

pass() {
    echo "  PASS  $1"
}

fail() {
    echo "  FAIL  $1"
    FAILURES+=("$2")
}

art() {
    # Run an artisan command from the project root with output visible.
    (cd "$PROJECT_PATH" && php artisan "$@")
}

echo "---- CHECK 01 dependencies: Composer and installed dependencies"
if ! command -v composer >/dev/null 2>&1; then
    fail "CHECK 01" "Composer is not available on PATH."
elif [ ! -f "$PROJECT_PATH/vendor/autoload.php" ]; then
    fail "CHECK 01" "vendor/autoload.php is missing; run 'composer install' in the project."
else
    if (cd "$PROJECT_PATH" && composer validate --no-check-all --no-interaction); then
        pass "composer.json is valid and vendor/autoload.php exists"
    else
        fail "CHECK 01" "'composer validate' failed; fix the Composer configuration or installation."
    fi
fi

echo "---- CHECK 02 configuration: .env, MySQL connection, APP_KEY"
if [ ! -f "$PROJECT_PATH/.env" ]; then
    fail "CHECK 02" ".env is missing in the project root."
else
    CONFIG_OK=1
    grep -Eq '^DB_CONNECTION=mysql' "$PROJECT_PATH/.env" || { fail "CHECK 02" ".env does not set DB_CONNECTION=mysql."; CONFIG_OK=0; }
    grep -Eq '^DB_DATABASE=[^[:space:]]+' "$PROJECT_PATH/.env" || { fail "CHECK 02" ".env does not set a non-empty DB_DATABASE."; CONFIG_OK=0; }
    grep -Eq '^APP_KEY=.+' "$PROJECT_PATH/.env" || { fail "CHECK 02" ".env does not set APP_KEY (run 'php artisan key:generate')."; CONFIG_OK=0; }
    [ "$CONFIG_OK" -eq 1 ] && pass ".env configures MySQL connection, database name, and APP_KEY"
fi

echo "---- CHECK 03 database: all migrations executed"
MIGRATE_STATUS_OUTPUT="$(art migrate:status --no-interaction 2>&1)"
MIGRATE_RC=$?
if [ "$MIGRATE_RC" -ne 0 ]; then
    fail "CHECK 03" "'php artisan migrate:status' failed (rc=$MIGRATE_RC): database unreachable or not configured."
elif printf '%s' "$MIGRATE_STATUS_OUTPUT" | grep -q 'Pending'; then
    fail "CHECK 03" "migrate:status reports Pending migrations; run 'php artisan migrate'."
else
    pass "database reachable and every migration has been executed"
fi
# Seeder evidence: the required test suite (CHECK 08) re-runs DatabaseSeeder
# with public $seed = true on every refresh (references/testing.md 7.1), so a
# passing CHECK 08 also proves migrations, seeders, and required
# configuration work end to end. Broken seeders surface here as CHECK 03 or
# CHECK 08 failures.

echo "---- CHECK 04 routes: route files compile and api/v1 is registered"
ROUTE_LIST_OUTPUT="$(art route:list 2>&1)"
ROUTE_RC=$?
if [ "$ROUTE_RC" -ne 0 ]; then
    fail "CHECK 04" "'php artisan route:list' failed (rc=$ROUTE_RC); a route file does not compile."
elif ! art route:list --path=api/v1 | grep -q 'api/v1'; then
    fail "CHECK 04" "no routes registered under the api/v1 prefix."
else
    pass "routes compile and the api/v1 prefix is registered"
fi

echo "---- CHECK 05 swagger: OpenAPI specification generates"
if art l5-swagger:generate --no-interaction; then
    pass "L5-Swagger generated the OpenAPI specification from the annotations"
else
    fail "CHECK 05" "'php artisan l5-swagger:generate' failed; Swagger annotations are invalid or incomplete."
fi

echo "---- CHECK 06 documentation: required docs files exist"
DOCS_OK=1
for DOC_FILE in \
    "$PROJECT_PATH/docs/API_DOCUMENTATION.md" \
    "$PROJECT_PATH/docs/DATABASE_DOCUMENTATION.md" \
    "$PROJECT_PATH/docs/IMPLEMENTATION_PLAN.md" \
    "$PROJECT_PATH/README.md"
do
    if [ ! -s "$DOC_FILE" ]; then
        fail "CHECK 06" "required documentation file is missing or empty: ${DOC_FILE#$PROJECT_PATH/}"
        DOCS_OK=0
    fi
done
[ "$DOCS_OK" -eq 1 ] && pass "API_DOCUMENTATION.md, DATABASE_DOCUMENTATION.md, IMPLEMENTATION_PLAN.md, README.md all exist"

# The generated API documentation must START with the Public
# Authentication Configuration section (SKILL.md, API and Swagger
# Documentation Requirement; references/api-documentation.md 4.1): the
# Frontend reads the runtime login configuration straight from the top of
# the documentation response, so the section may not sit at the end of the
# file or be missing from it. Its values must be the seeded First Party
# PKCE Client's actual database values, never hardcoded or invented ones.
API_DOC="$PROJECT_PATH/docs/API_DOCUMENTATION.md"
if [ -s "$API_DOC" ]; then
    DOC_SECTION_OK=1
    DOC_FIRST_LINE="$(grep -m1 -v '^[[:space:]]*$' "$API_DOC")"
    DOC_FIRST_LINE="${DOC_FIRST_LINE%$'\r'}"
    DOC_SECTION_BLOCK="$(awk '{sub(/\r$/,"")} /^---$/{exit} {print}' "$API_DOC")"
    if [ "$DOC_FIRST_LINE" != "## Public Authentication Configuration" ]; then
        fail "CHECK 06" "docs/API_DOCUMENTATION.md must START with the Public Authentication Configuration section (its first non-empty line is: ${DOC_FIRST_LINE:-<empty>}); see references/api-documentation.md 4.1."
        DOC_SECTION_OK=0
    fi
    printf '%s\n' "$DOC_SECTION_BLOCK" | grep -qFe '- `client_id`:' || {
        fail "CHECK 06" "the Public Authentication Configuration section at the top of docs/API_DOCUMENTATION.md does not document client_id."
        DOC_SECTION_OK=0
    }
    printf '%s\n' "$DOC_SECTION_BLOCK" | grep -qFe '- `redirect_uri`:' || {
        fail "CHECK 06" "the Public Authentication Configuration section at the top of docs/API_DOCUMENTATION.md does not document redirect_uri."
        DOC_SECTION_OK=0
    }
    printf '%s\n' "$DOC_SECTION_BLOCK" | grep -qFe '- `code_challenge_method`: `S256`' || {
        fail "CHECK 06" "the Public Authentication Configuration section at the top of docs/API_DOCUMENTATION.md does not document code_challenge_method S256."
        DOC_SECTION_OK=0
    }
    # The section values must come from the database: resolve the seeded
    # First Party PKCE Client from the project database and compare.
    if [ "$DOC_SECTION_OK" -eq 1 ]; then
        SEEDED_CLIENT_LINE="$(cd "$PROJECT_PATH" && php -r '
require "vendor/autoload.php";
$app = require "bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$client = Laravel\Passport\Client::where("name", "First Party PKCE Client")->where("revoked", false)->first();
if (! $client) { echo "CLIENT_MISSING"; exit; }
echo $client->id."|".($client->redirect_uris[0] ?? "");
' 2>/dev/null)"
        if [ "$SEEDED_CLIENT_LINE" = "CLIENT_MISSING" ] || [ -z "$SEEDED_CLIENT_LINE" ]; then
            fail "CHECK 06" "the seeded First Party PKCE Client could not be resolved from the project database (seed the clients with 'php artisan db:seed'); the documentation cannot carry its runtime client_id and redirect_uri."
            DOC_SECTION_OK=0
        else
            DB_CLIENT_ID="${SEEDED_CLIENT_LINE%%|*}"
            DB_REDIRECT_URI="${SEEDED_CLIENT_LINE#*|}"
            printf '%s\n' "$DOC_SECTION_BLOCK" | grep -qF "$DB_CLIENT_ID" || {
                fail "CHECK 06" "the documentation's client_id does not match the seeded First Party PKCE Client id in the database ($DB_CLIENT_ID); the section values must be resolved from the database, never hardcoded."
                DOC_SECTION_OK=0
            }
            if [ -n "$DB_REDIRECT_URI" ] && ! printf '%s\n' "$DOC_SECTION_BLOCK" | grep -qF "$DB_REDIRECT_URI"; then
                fail "CHECK 06" "the documentation's redirect_uri does not match the client's first registered redirect_uri in the database ($DB_REDIRECT_URI)."
                DOC_SECTION_OK=0
            fi
        fi
    fi
    [ "$DOC_SECTION_OK" -eq 1 ] && pass "API documentation starts with the Public Authentication Configuration section carrying the seeded client's database values (client_id, redirect_uri, S256)"
fi

echo "---- CHECK 07 standards: approved structure machine checks"
STANDARDS_OK=1
if [ ! -f "$PROJECT_PATH/app/Models/V1/User.php" ]; then
    fail "CHECK 07" "app/Models/V1/User.php is missing; the single V1 User model standard is violated."
    STANDARDS_OK=0
fi
if [ -f "$PROJECT_PATH/app/Models/User.php" ]; then
    fail "CHECK 07" "app/Models/User.php exists; the default User model must never be recreated."
    STANDARDS_OK=0
fi
if [ ! -f "$PROJECT_PATH/database/factories/V1/UserFactory.php" ]; then
    fail "CHECK 07" "database/factories/V1/UserFactory.php is missing."
    STANDARDS_OK=0
fi
if [ -f "$PROJECT_PATH/database/factories/UserFactory.php" ]; then
    fail "CHECK 07" "database/factories/UserFactory.php exists; the default UserFactory must never be recreated."
    STANDARDS_OK=0
fi
# Approved authentication configuration (references/authentication.md):
# default guard api, and a passport-driver api guard on the users provider.
AUTH_CONFIG="$PROJECT_PATH/config/auth.php"
if [ ! -f "$AUTH_CONFIG" ]; then
    fail "CHECK 07" "config/auth.php is missing."
    STANDARDS_OK=0
else
    grep -qF "env('AUTH_GUARD', 'api')" "$AUTH_CONFIG" || {
        fail "CHECK 07" "config/auth.php default guard is not 'api'."
        STANDARDS_OK=0
    }
    grep -qF "'driver' => 'passport'" "$AUTH_CONFIG" || {
        fail "CHECK 07" "config/auth.php has no passport-driver api guard."
        STANDARDS_OK=0
    }
    grep -q '^use App.Models.V1.User;' "$AUTH_CONFIG" || {
        fail "CHECK 07" "config/auth.php users provider does not reference App\\Models\\V1\\User."
        STANDARDS_OK=0
    }
fi
# Token lifecycle configuration (references/authentication.md, Token
# Lifecycle): TTL config keys and the AppServiceProvider TTL registration.
OAUTH_CONFIG="$PROJECT_PATH/config/oauth.php"
if [ ! -f "$OAUTH_CONFIG" ]; then
    fail "CHECK 07" "config/oauth.php is missing; token lifetimes are not configured."
    STANDARDS_OK=0
else
    grep -qF "'access_token_expires_in'" "$OAUTH_CONFIG" \
        && grep -qF "'refresh_token_expires_in'" "$OAUTH_CONFIG" || {
        fail "CHECK 07" "config/oauth.php does not define the access and refresh token lifetime keys."
        STANDARDS_OK=0
    }
fi
APP_PROVIDER="$PROJECT_PATH/app/Providers/AppServiceProvider.php"
if [ ! -f "$APP_PROVIDER" ]; then
    fail "CHECK 07" "app/Providers/AppServiceProvider.php is missing."
    STANDARDS_OK=0
else
    grep -qF "Passport::tokensExpireIn" "$APP_PROVIDER" \
        && grep -qF "Passport::refreshTokensExpireIn" "$APP_PROVIDER" || {
        fail "CHECK 07" "AppServiceProvider.php does not register Passport::tokensExpireIn and Passport::refreshTokensExpireIn."
        STANDARDS_OK=0
    }
    # Passport route hardening (references/authentication.md, Passport route
    # hardening): automatic Passport routes disabled, and the application
    # registers exactly one OAuth endpoint itself through Passport's own
    # AccessTokenController, keeping the throttle middleware.
    grep -qF "Passport::ignoreRoutes" "$APP_PROVIDER" || {
        fail "CHECK 07" "AppServiceProvider.php does not call Passport::ignoreRoutes; Passport's automatic OAuth routes are still registered."
        STANDARDS_OK=0
    }
    grep -qF "AccessTokenController::class" "$APP_PROVIDER" \
        && grep -qF "'/oauth/token'" "$APP_PROVIDER" \
        && grep -qFe "->middleware('throttle')" "$APP_PROVIDER" || {
        fail "CHECK 07" "AppServiceProvider.php does not register POST /oauth/token through Passport's AccessTokenController with the throttle middleware."
        STANDARDS_OK=0
    }
    # Rate limiting (references/api-standards.md, Rate Limiting): the api
    # baseline limiter on the middleware group, the api/auth limiter
    # definitions, and the approved config keys.
    grep -qF "RateLimiter::for('api'" "$APP_PROVIDER" \
        && grep -qF "RateLimiter::for('auth'" "$APP_PROVIDER" \
        && grep -qF "RateLimiter::for('password_reset'" "$APP_PROVIDER" \
        && grep -qF "RateLimiter::for('email_resend'" "$APP_PROVIDER" || {
        fail "CHECK 07" "AppServiceProvider.php does not define the api, auth, password_reset, and email_resend rate limiters."
        STANDARDS_OK=0
    }
    # Password reset link URL (references/authentication.md, Password Reset):
    # the native ResetPassword notification is pointed at the frontend page
    # configured through app.frontend_url (no password.reset web route
    # lookup, which would fail in the API-only project).
    grep -qF "ResetPassword::createUrlUsing" "$APP_PROVIDER" || {
        fail "CHECK 07" "AppServiceProvider.php does not build the native reset link through ResetPassword::createUrlUsing."
        STANDARDS_OK=0
    }
fi
RATE_LIMIT_CONFIG="$PROJECT_PATH/config/rate_limiting.php"
if [ ! -f "$RATE_LIMIT_CONFIG" ]; then
    fail "CHECK 07" "config/rate_limiting.php is missing; the approved rate limits are not configured."
    STANDARDS_OK=0
else
    grep -qF "'api_per_minute'" "$RATE_LIMIT_CONFIG" \
        && grep -qF "'auth_per_minute'" "$RATE_LIMIT_CONFIG" \
        && grep -qF "'auth_per_hour'" "$RATE_LIMIT_CONFIG" \
        && grep -qF "'password_reset_per_minute'" "$RATE_LIMIT_CONFIG" \
        && grep -qF "'password_reset_per_hour'" "$RATE_LIMIT_CONFIG" \
        && grep -qF "'email_resend_per_minute'" "$RATE_LIMIT_CONFIG" \
        && grep -qF "'email_resend_per_hour'" "$RATE_LIMIT_CONFIG" || {
        fail "CHECK 07" "config/rate_limiting.php does not define the approved api, auth, password_reset, and email_resend limit keys."
        STANDARDS_OK=0
    }
fi
grep -qF "throttleApi" "$PROJECT_PATH/bootstrap/app.php" || {
    fail "CHECK 07" "bootstrap/app.php does not enable \$middleware->throttleApi(); the /api/v1 routes have no baseline rate limit."
    STANDARDS_OK=0
}
grep -qF "throttle:auth" "$PROJECT_PATH/routes/v1/auth.php" || {
    fail "CHECK 07" "routes/v1/auth.php does not apply throttle:auth to the login endpoint."
    STANDARDS_OK=0
}
grep -qF "'rate_limited'" "$PROJECT_PATH/lang/en/messages.php" \
    && grep -qF "'rate_limited'" "$PROJECT_PATH/lang/ar/messages.php" || {
    fail "CHECK 07" "the rate_limited message key is missing from lang/en/messages.php or lang/ar/messages.php."
    STANDARDS_OK=0
}
# Password reset and email verification message keys (references/
# localization.md; references/authentication.md, Password Reset and Email
# Verification): en and ar carry the localized flow messages.
for MESSAGE_KEY in password_reset_link_sent password_reset_successfully invalid_reset_token email_verification_sent email_already_verified invalid_verification_link email_verified_successfully; do
    grep -qF "'$MESSAGE_KEY'" "$PROJECT_PATH/lang/en/messages.php" \
        && grep -qF "'$MESSAGE_KEY'" "$PROJECT_PATH/lang/ar/messages.php" || {
        fail "CHECK 07" "the '$MESSAGE_KEY' message key is missing from lang/en/messages.php or lang/ar/messages.php."
        STANDARDS_OK=0
    }
done
# Native password reset (references/authentication.md, Password Reset): the
# broker is the only reset implementation and the two reset requests ship.
grep -qF "Password::sendResetLink" "$PROJECT_PATH/app/Services/V1/AuthService.php" \
    && grep -qF "Password::reset" "$PROJECT_PATH/app/Services/V1/AuthService.php" || {
    fail "CHECK 07" "AuthService.php does not implement the password reset through Laravel's native Password broker."
    STANDARDS_OK=0
}
for RESET_REQUEST_FILE in \
    "$PROJECT_PATH/app/Http/Requests/V1/Auth/ForgotPasswordRequest.php" \
    "$PROJECT_PATH/app/Http/Requests/V1/Auth/ResetPasswordRequest.php"
do
    if [ ! -s "$RESET_REQUEST_FILE" ]; then
        fail "CHECK 07" "password reset request file is missing or empty: ${RESET_REQUEST_FILE#$PROJECT_PATH/}"
        STANDARDS_OK=0
    fi
done
# Default register endpoint (references/authentication.md, Registration):
# the public register route with the auth limiter, the register action, the
# service creation with the automatic native verification email, and the
# RegisterRequest template ship with the scaffold.
grep -qF "Route::post('/register', 'register')" "$PROJECT_PATH/routes/v1/auth.php" \
    && grep -qF "public function register(RegisterRequest" "$PROJECT_PATH/app/Http/Controllers/V1/AuthController.php" \
    && grep -qF "public function register(array" "$PROJECT_PATH/app/Services/V1/AuthService.php" \
    || {
    fail "CHECK 07" "the default register endpoint is missing (register route with throttle:auth, AuthController register action, AuthService register with the automatic verification email)."
    STANDARDS_OK=0
}
if [ ! -s "$PROJECT_PATH/app/Http/Requests/V1/Auth/RegisterRequest.php" ]; then
    fail "CHECK 07" "app/Http/Requests/V1/Auth/RegisterRequest.php is missing or empty."
    STANDARDS_OK=0
fi
# Native email verification (references/authentication.md, Email
# Verification): the User implements MustVerifyEmail, the signed
# verification.verify route addresses the user by uuid through the
# VerifyEmail::createUrlUsing() hook, the resend endpoint sits inside the
# auth:api group, and signed-link rejections render the approved envelope.
grep -qF "implements MustVerifyEmail" "$PROJECT_PATH/app/Models/V1/User.php" || {
    fail "CHECK 07" "app/Models/V1/User.php does not implement MustVerifyEmail; the native email verification flow cannot work."
    STANDARDS_OK=0
}
grep -qF "'verification.verify'" "$PROJECT_PATH/routes/v1/auth.php" \
    && grep -qF "email/verify/{uuid}/{hash}" "$PROJECT_PATH/routes/v1/auth.php" \
    && grep -qF "middleware('signed')" "$PROJECT_PATH/routes/v1/auth.php" \
    && grep -qF "forgot-password" "$PROJECT_PATH/routes/v1/auth.php" \
    && grep -qF "reset-password" "$PROJECT_PATH/routes/v1/auth.php" \
    && grep -qF "email/resend" "$PROJECT_PATH/routes/v1/auth.php" \
    && grep -qF "throttle:password_reset" "$PROJECT_PATH/routes/v1/auth.php" \
    && grep -qF "throttle:email_resend" "$PROJECT_PATH/routes/v1/auth.php" || {
    fail "CHECK 07" "routes/v1/auth.php is missing the password reset and email verification routes (forgot-password, reset-password, signed verification.verify with the uuid parameter, email/resend with their throttles)."
    STANDARDS_OK=0
}
grep -qF "VerifyEmail::createUrlUsing" "$PROJECT_PATH/app/Providers/AppServiceProvider.php" || {
    fail "CHECK 07" "AppServiceProvider.php does not register VerifyEmail::createUrlUsing; the native notification would build its signed link with the database id instead of the uuid (and throw MissingRouteParameterException against the {uuid} route)."
    STANDARDS_OK=0
}
grep -qF "sendEmailVerificationNotification" "$PROJECT_PATH/app/Services/V1/AuthService.php" \
    && grep -qF "markEmailAsVerified" "$PROJECT_PATH/app/Services/V1/AuthService.php" || {
    fail "CHECK 07" "AuthService.php does not implement the email verification through Laravel's native MustVerifyEmail mechanics."
    STANDARDS_OK=0
}
grep -qF "InvalidSignatureException" "$PROJECT_PATH/bootstrap/app.php" || {
    fail "CHECK 07" "bootstrap/app.php does not render InvalidSignatureException as the approved envelope; expired or tampered verification links leak the native English message."
    STANDARDS_OK=0
}
# Fortify is not part of the approved architecture (references/
# authentication.md, Email Verification): Laravel's native machinery plus
# Passport own every auth flow, and Fortify's emailVerification feature
# would register a second session-auth route under the same
# `verification.verify` name the framework's VerifyEmail notification
# resolves through URL::temporarySignedRoute(). The dependency and its
# config must stay absent from the generated project.
if test -f "$PROJECT_PATH/config/fortify.php" \
    || grep -qiF "laravel/fortify" "$PROJECT_PATH/composer.json"; then
    fail "CHECK 07" "Fortify is not part of the approved architecture: laravel/fortify must be absent from composer.json and config/fortify.php must not exist (Features::emailVerification hijacks the verification.verify route name and breaks every emailed verification link)."
    STANDARDS_OK=0
fi
# The registration, password reset, and email verification feature specs
# ship with the scaffold (references/testing.md §9).
for AUTH_FLOW_TEST in \
    "$PROJECT_PATH/tests/Feature/V1/RegisterTest.php" \
    "$PROJECT_PATH/tests/Feature/V1/PasswordResetTest.php" \
    "$PROJECT_PATH/tests/Feature/V1/EmailVerificationTest.php"
do
    if [ ! -s "$AUTH_FLOW_TEST" ]; then
        fail "CHECK 07" "authentication flow test file is missing or empty: ${AUTH_FLOW_TEST#$PROJECT_PATH/}"
        STANDARDS_OK=0
    fi
done
# The live route table must match the hardening contract: exactly one
# oauth-prefixed route — POST oauth/token — and nothing else under oauth/
# (the disabled authorize/consent, personal-token, scope, and device routes
# must not reappear). L5-Swagger's api/oauth2-callback does not match ^oauth/
# and is excluded by the prefix filter.
OAUTH_ROUTES_JSON="$(art route:list --path=oauth --json 2>/dev/null)"
OAUTH_ROUTE_TABLE_OK="$(printf '%s' "$OAUTH_ROUTES_JSON" | php -r '
$json = json_decode(stream_get_contents(STDIN), true) ?: [];
$token = array_values(array_filter($json, fn ($r) => ($r["uri"] ?? "") === "oauth/token"));
$extra = array_filter($json, fn ($r) => ($r["uri"] ?? "") !== "oauth/token" && str_starts_with($r["uri"] ?? "", "oauth/"));
if (count($token) === 1 && ($token[0]["method"] ?? "") === "POST" && $extra === []) {
    echo "OK";
}')"
if [ "$OAUTH_ROUTE_TABLE_OK" != "OK" ]; then
    fail "CHECK 07" "the route table does not match the Passport route hardening contract: exactly one OAuth route (POST /oauth/token) must exist; all other Passport routes must be disabled (404)."
    STANDARDS_OK=0
fi
# The OAuth client contract: the seeder must create the public first-party
# client with exactly the authorization_code + refresh_token grants.
PASSPORT_SEEDER="$PROJECT_PATH/database/seeders/PassportClientSeeder.php"
if [ ! -f "$PASSPORT_SEEDER" ]; then
    fail "CHECK 07" "database/seeders/PassportClientSeeder.php is missing."
    STANDARDS_OK=0
else
    grep -qF "'authorization_code'" "$PASSPORT_SEEDER" \
        && grep -qF "'refresh_token'" "$PASSPORT_SEEDER" \
        && grep -qF "First Party PKCE Client" "$PASSPORT_SEEDER" || {
        fail "CHECK 07" "PassportClientSeeder.php does not seed the public First Party PKCE Client with the authorization_code and refresh_token grants."
        STANDARDS_OK=0
    }
fi
# The client seeder must be registered in DatabaseSeeder: it is the single
# authoritative creator of the OAuth clients, and the lifecycle tests resolve
# the First Party PKCE Client through seeding in main and test databases
# alike (references/authentication.md, Token Lifecycle; references/testing.md
# section 10). WithoutModelEvents must be gone: it suppresses the V1 User's
# creating hook and inserts seeded users without their required uuid.
DATABASE_SEEDER="$PROJECT_PATH/database/seeders/DatabaseSeeder.php"
if [ ! -f "$DATABASE_SEEDER" ]; then
    fail "CHECK 07" "database/seeders/DatabaseSeeder.php is missing."
    STANDARDS_OK=0
else
    grep -qF "PassportClientSeeder::class" "$DATABASE_SEEDER" || {
        fail "CHECK 07" "DatabaseSeeder.php does not register PassportClientSeeder; the OAuth clients are never seeded."
        STANDARDS_OK=0
    }
    grep -qF "WithoutModelEvents" "$DATABASE_SEEDER" && {
        fail "CHECK 07" "DatabaseSeeder.php still uses WithoutModelEvents; seeded users lose their uuid and the lifecycle tests cannot bootstrap."
        STANDARDS_OK=0
    }
fi
# The Auth module ships with the scaffold (Token Lifecycle): routes,
# controller, service, request, and the lifecycle feature test.
for AUTH_FILE in \
    "$PROJECT_PATH/routes/v1/auth.php" \
    "$PROJECT_PATH/app/Http/Controllers/V1/AuthController.php" \
    "$PROJECT_PATH/app/Services/V1/AuthService.php" \
    "$PROJECT_PATH/app/Http/Requests/V1/Auth/LoginRequest.php" \
    "$PROJECT_PATH/tests/Feature/V1/AuthTest.php"
do
    if [ ! -s "$AUTH_FILE" ]; then
        fail "CHECK 07" "token lifecycle file is missing or empty: ${AUTH_FILE#$PROJECT_PATH/}"
        STANDARDS_OK=0
    fi
done
# The public authentication configuration (references/authentication.md, Token
# Lifecycle): the documentation endpoint resolves the seeded public First
# Party PKCE Client and serves its client_id, redirect_uri, and S256 method;
# the endpoint spec ships with the scaffold.
DOC_CONTROLLER="$PROJECT_PATH/app/Http/Controllers/V1/DocumentationController.php"
if [ ! -s "$DOC_CONTROLLER" ]; then
    fail "CHECK 07" "app/Http/Controllers/V1/DocumentationController.php is missing."
    STANDARDS_OK=0
else
    grep -qF "First Party PKCE Client" "$DOC_CONTROLLER" \
        && grep -qF "S256" "$DOC_CONTROLLER" || {
        fail "CHECK 07" "DocumentationController.php does not expose the public authentication configuration of the First Party PKCE Client (client_id, redirect_uri, code_challenge_method S256)."
        STANDARDS_OK=0
    }
fi
if [ ! -s "$PROJECT_PATH/tests/Feature/V1/DocumentationTest.php" ]; then
    fail "CHECK 07" "tests/Feature/V1/DocumentationTest.php is missing; the public authentication configuration spec does not ship with the scaffold."
    STANDARDS_OK=0
fi
# Approved authorization configuration (references/architecture.md, Package
# Models Requiring Standard Columns): Spatie pointed at the V1 models.
PERMISSION_CONFIG="$PROJECT_PATH/config/permission.php"
for V1_PACKAGE_MODEL in Role Permission; do
    if [ ! -f "$PROJECT_PATH/app/Models/V1/$V1_PACKAGE_MODEL.php" ]; then
        fail "CHECK 07" "app/Models/V1/$V1_PACKAGE_MODEL.php is missing; the extended Spatie model standard is violated."
        STANDARDS_OK=0
    fi
done
if [ ! -f "$PERMISSION_CONFIG" ]; then
    fail "CHECK 07" "config/permission.php is missing; run the Spatie vendor:publish step."
    STANDARDS_OK=0
else
    grep -q '^use App.Models.V1.Role;' "$PERMISSION_CONFIG" \
        && grep -q '^use App.Models.V1.Permission;' "$PERMISSION_CONFIG" || {
        fail "CHECK 07" "config/permission.php does not import App\\Models\\V1\\Role and App\\Models\\V1\\Permission."
        STANDARDS_OK=0
    }
    grep -qF "'role' => Role::class," "$PERMISSION_CONFIG" \
        && grep -qF "'permission' => Permission::class," "$PERMISSION_CONFIG" || {
        fail "CHECK 07" "config/permission.php models.role / models.permission do not resolve to the V1 models."
        STANDARDS_OK=0
    }
fi
# Authentication middleware placement (references/authentication.md,
# Authentication Route Middleware): applied at the module route group level,
# never inside a Controller constructor.
if grep -rF "middleware('auth:api'" "$PROJECT_PATH/app/Http/Controllers" >/dev/null 2>&1; then
    fail "CHECK 07" "a Controller applies auth:api middleware in its constructor; authentication middleware belongs on the module route group."
    STANDARDS_OK=0
fi
for ROUTE_FILE in "$PROJECT_PATH/routes/v1/"*.php; do
    [ "$(basename "$ROUTE_FILE")" = "documentation.php" ] && continue
    if ! grep -qF 'auth:api' "$ROUTE_FILE"; then
        fail "CHECK 07" "module route file ${ROUTE_FILE#$PROJECT_PATH/} does not apply auth:api middleware on its route group."
        STANDARDS_OK=0
    fi
done
if grep -q '{{' "$PROJECT_PATH/README.md" 2>/dev/null; then
    fail "CHECK 07" "README.md still contains unresolved {{...}} placeholders."
    STANDARDS_OK=0
fi
[ "$STANDARDS_OK" -eq 1 ] && pass "single V1 User model/factory in place, auth and permission configs approved, token lifecycle files and OAuth client contract in place, default register endpoint with the automatic verification email installed, native password reset and uuid-based email verification features installed, Passport route hardening contract (only POST /oauth/token registered), approved rate limiters configured (throttleApi baseline, throttle:auth login and register, throttle:password_reset, throttle:email_resend, config/rate_limiting.php), route-level auth:api placement, README has no unresolved placeholders"

echo "---- CHECK 08 tests: complete required test suite"
# Sequential run (references/testing.md 14): tests share one MySQL test
# database; never use --parallel. Note: no --no-interaction here —
# `artisan test` forwards unknown options to PHPUnit, and PHPUnit 12
# rejects --no-interaction.
if art test; then
    pass "the complete test suite passed"
else
    fail "CHECK 08" "'php artisan test' failed; the test suite does not fully pass (criterion 6)."
fi

echo "---- CHECK 09 boot: application boots and serves the documentation endpoint"
SERVE_PID=""
cleanup_server() {
    if [ -n "$SERVE_PID" ]; then
        # Tree-kill first: 'php artisan serve' spawns a 'php -S' child that a
        # plain kill would orphan on Windows, keeping the port busy.
        if command -v taskkill >/dev/null 2>&1; then
            taskkill //F //T //PID "$SERVE_PID" >/dev/null 2>&1 || true
        fi
        kill "$SERVE_PID" 2>/dev/null
        wait "$SERVE_PID" 2>/dev/null
        SERVE_PID=""
    fi
}
trap cleanup_server EXIT

(cd "$PROJECT_PATH" && php artisan serve --host=127.0.0.1 --port="$GATE_PORT") &
SERVE_PID=$!

SMOKE_CODE="000"
for _ in $(seq 1 30); do
    SMOKE_CODE="$(curl -s -o /dev/null -w '%{http_code}' "$SMOKE_URL" 2>/dev/null)"
    [ "$SMOKE_CODE" != "000" ] && break
    sleep 1
done

if [ "$SMOKE_CODE" = "200" ]; then
    SMOKE_BODY="$(curl -s "$SMOKE_URL" 2>/dev/null)"
    SMOKE_FIRST_LINE="$(printf '%s' "$SMOKE_BODY" | head -n1)"
    SMOKE_FIRST_LINE="${SMOKE_FIRST_LINE%$'\r'}"
    if [ "$SMOKE_FIRST_LINE" = "## Public Authentication Configuration" ] \
        && printf '%s' "$SMOKE_BODY" | grep -qF '`client_id`:' \
        && printf '%s' "$SMOKE_BODY" | grep -qF '`redirect_uri`:' \
        && printf '%s' "$SMOKE_BODY" | grep -qF '`code_challenge_method`: `S256`'; then
        pass "application boots; GET /api/v1/documentation returned 200 beginning with the Public Authentication Configuration (client_id, redirect_uri, S256)"
    else
        fail "CHECK 09" "GET /api/v1/documentation returned 200 but the response does not begin with the Public Authentication Configuration section carrying client_id, redirect_uri, and code_challenge_method S256."
    fi
elif [ "$SMOKE_CODE" = "000" ]; then
    fail "CHECK 09" "the application did not answer on port $GATE_PORT within 30s (set GATE_PORT=<free port> if the port is busy)."
else
    fail "CHECK 09" "GET /api/v1/documentation answered HTTP $SMOKE_CODE instead of 200; the app boots but the documentation endpoint does not serve docs/API_DOCUMENTATION.md correctly."
fi
cleanup_server

echo "============================================================"
if [ "${#FAILURES[@]}" -eq 0 ]; then
    echo " RESULT: READY"
    echo " All machine checks passed. Confirm the judgment checks from"
    echo " SKILL.md (requirement coverage, documentation accuracy,"
    echo " authentication and integration correctness), then the project"
    echo " may be reported READY and handed off."
    echo "============================================================"
    exit 0
else
    echo " RESULT: BLOCKED (${#FAILURES[@]} failure(s))"
    for FAILURE in "${FAILURES[@]}"; do
        echo "   - $FAILURE"
    done
    echo " The project is NOT ready. Fix the failures above, re-run:"
    echo "   bash completion-gate.sh $PROJECT_NAME"
    echo " A BLOCKED project must not be handed off to the Frontend Team"
    echo " or any other team."
    echo "============================================================"
    exit 1
fi
