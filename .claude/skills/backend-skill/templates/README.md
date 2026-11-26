# {{PROJECT_NAME}}

{{PROJECT_DESCRIPTION}}

## Project Status

This project is `BLOCKED` by default. It becomes `READY` only after the Backend Skill Completion Gate passes, which requires:

- All Backend Skill requirements fully implemented.
- Composer and all dependencies installed successfully.
- Database setup, migrations, seeders, and required configurations completed.
- All routes, authentication, and integrations working correctly.
- Swagger and all required documentation generated and verified.
- The complete required test suite passing.
- The application booting and running successfully.

Run the gate from the skill workspace: `bash scripts/completion-gate.sh <project-folder-name>`. A `BLOCKED` project must not be handed off to the Frontend Team or any other team. Update this section to `READY` only after the gate passes.

## Requirements

- PHP ^8.4
- Composer
- MySQL
- Laravel ^13.0

## Setup

1. Install dependencies:

    ```bash
    composer install
    ```

    Wait for this step to finish before running any `php artisan` command. While Composer is still installing, the project has no `vendor/autoload.php` yet and `php artisan serve` fails with "Failed opening required .../vendor/autoload.php".

2. Configure the environment:

    ```bash
    cp .env.example .env
    php artisan key:generate
    ```

    Database connection: MySQL, configured in this project's `.env` (`DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`). The scaffold filled these from the Backend Skill's developer-specific `local.config.json` at setup time; `.env` is git-ignored and credentials are never committed. The configured database name is `{{DB_NAME}}`.

3. Run the migrations:

    ```bash
    php artisan migrate
    ```

4. Prepare Laravel Passport:

    ```bash
    php artisan passport:keys
    ```

5. Run the seeders after the module seeders are registered. Seeding creates both OAuth clients (`database/seeders/PassportClientSeeder.php`): the personal access client and the public First Party PKCE Client used by the login flow:

    ```bash
    php artisan db:seed
    ```

6. Start the local development server:

    ```bash
    php artisan serve
    ```

## API Documentation

- Swagger UI: `{APP_URL}/api/documentation` (L5-Swagger default route)
- Public documentation endpoint: `GET {APP_URL}/api/v1/documentation` (serves `docs/API_DOCUMENTATION.md` beginning with the **Public Authentication Configuration** section — the live `client_id`, `redirect_uri`, and `code_challenge_method: S256` to use in the login flow below — no authentication required)
- Markdown documentation file: `docs/API_DOCUMENTATION.md`

## Authentication

Laravel Passport is the default API authentication system. Roles and permissions are managed with Spatie Laravel Permission.

Login uses the OAuth 2.0 authorization code grant with PKCE (references/authentication.md, Token Lifecycle):

1. `POST /api/v1/auth/login` — send `email`, `password`, the First Party PKCE Client `client_id`, `redirect_uri`, and a PKCE `code_challenge` (`S256`); the response returns an authorization `code`.
2. `POST /oauth/token` with `grant_type=authorization_code` and exactly five fields: the `client_id`, the `redirect_uri` (required — byte-identical to the value sent at login, RFC 6749 §4.1.3), the `code`, and the matching `code_verifier` — returns `access_token` and `refresh_token`. Any missing field answers `400 invalid_request` with a `hint` naming it (`Check the redirect_uri parameter`).
3. Refresh before expiry: `POST /oauth/token` with `grant_type=refresh_token`, the `client_id`, and the current `refresh_token` — every refresh issues a NEW token pair and revokes the old one.
4. `POST /api/v1/auth/logout` (Bearer access token) — revokes the access token and its associated refresh token.

Token lifetimes are configured in `config/oauth.php` (`OAUTH_ACCESS_TOKEN_EXPIRES_IN` minutes, default 15; `OAUTH_REFRESH_TOKEN_EXPIRES_IN` days, default 30). The client is public: frontends need only its id, never a secret.

### Registration (Laravel native email verification)

- `POST /api/v1/auth/register` with `name`, `email` (unique), `password`, `password_confirmation` — creates the account (min 8-character confirmed password) and automatically sends the verification email through Laravel's native `MustVerifyEmail` machinery. The endpoint does not log the user in and issues no tokens; log in through the PKCE flow afterwards. A duplicate email answers 422, and the endpoint runs under the same `throttle:auth` limiter as login.

### Password reset (Laravel native)

1. `POST /api/v1/auth/forgot-password` with `email` — sends the reset link through Laravel's native Password broker. The answer is the same whether or not the address is registered (no account enumeration); a second link for the same address within 60 seconds answers 429.
2. The email link opens the frontend reset-password page (`FRONTEND_URL`, default: the API URL) with `token` and `email` in the query.
3. `POST /api/v1/auth/reset-password` with `token`, `email`, `password`, `password_confirmation` — the token is single-use and expires after 60 minutes (`auth.passwords.users.expire`).

### Email verification (Laravel native)

- `GET /api/v1/auth/email/verify/{uuid}/{hash}?expires=...&signature=...` — the signed link Laravel's native `VerifyEmail` notification emails (the link addresses the user by uuid). Following it stamps `email_verified_at`; the signature is valid for 60 minutes, and expired or tampered links answer 403.
- `POST /api/v1/auth/email/resend` (Bearer access token) — re-sends the verification email for the authenticated user; already-verified accounts answer 422.
- `GET /api/v1/auth/me` includes `email_verified_at` so clients know the verification status.

## Localization

`en` is the only default language and always the fallback locale. The project ships no `lang` directory and no translation files: API and authentication messages are plain English strings handled directly in their implementation (the central exception handler and the Auth module). The `Accept-Language` header is still honored through the `SetLocale` middleware, but with its supported-language list `['en']` every request resolves to `en`.

Additional languages are generated only when the project requirements explicitly request them: `lang/en/messages.php` plus `lang/{locale}/messages.php` per requested locale, the inline literals converted to `__('messages.*')` calls, and each requested locale added to the supported-language list in the `SetLocale` middleware (see `.claude/skills/backend-skill/references/localization.md` in the skill repository).
