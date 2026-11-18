# Authentication Standards

## Default API Authentication
- Laravel Passport is the approved default API authentication system.
- Passport must be included in the base project dependencies.
- Prepare the project to use Passport for API authentication.
- Do not replace Passport with Sanctum or another authentication system.
- Keep roles and permissions on the `api` guard: set `auth.defaults.guard` to `api`, define the Passport `api` driver guard in `config/auth.php`, and create all Spatie roles and permissions with `guard_name` `api` (the seeder should read the guard from `config('auth.defaults.guard')`).

## Authentication Features
Authentication features must be implemented according to the project's business requirements.

Do not assume that every optional authentication feature is required.

## Google Login
- Google login is an optional feature.
- Implement it only when explicitly required by the business requirements.
- Do not assume a Google authentication package or integration mechanism that has not been approved.

## Phone OTP Verification
- Phone OTP verification is optional.
- When required, integrate it with the User Model and users table.
- Twilio is the approved default SMS provider.
- Use the existing Twilio SDK dependency.
- Store OTP values securely; never store plain-text OTP values.
- Keep provider credentials and secrets in environment variables.
- Do not hardcode credentials in application source code.

## Unconfirmed Authentication Decisions
The following decisions have not yet been finalized:
- Whether Email/Password login is enabled by default.
- The exact Google login implementation and provider package.
- The complete OTP lifecycle, expiration, retry, and rate-limiting rules.

Do not invent these decisions. Ask for clarification when they are required for implementation.

## Passport 13 Setup Sequence

Laravel Passport 13 publishes its migrations instead of loading them automatically, and `passport:install` re-runs migrations internally. Never run `passport:install` on a database that already contains the oauth tables; it fails with "table already exists".

Run the following commands in this exact order:

1. `php artisan vendor:publish --tag=passport-migrations` — Passport 13 does not auto-load its migrations.
2. `php artisan vendor:publish --tag=telescope-migrations` — then delete any duplicated `*_create_oauth_*` migration files this publish may re-copy (keep exactly one migration per oauth table; the earliest timestamp).
3. `php artisan migrate`
4. `php artisan passport:keys`
5. `php artisan passport:client --personal --name="Personal Access Client"`
6. `php artisan db:seed` — only after the project's Roles and Permissions Seeder is registered in `DatabaseSeeder`.

Additional rules:

- `scripts/scaffold.sh` implements this sequence automatically.
- Issue tokens with the User model's `HasApiTokens` trait (`$user->createToken(...)`); do not rely on the Passport password grant.
- When publishing package configs, verify the actual publish tag against the package provider. Known mismatch: `l5-swagger` has no `l5-swagger-config` tag — copy `vendor/darkaonline/l5-swagger/config/l5-swagger.php` to `config/l5-swagger.php` directly.
- On Laravel 13 the skeleton has no `app/Http/Middleware/` directory; create it before copying the approved `SetLocale` template.