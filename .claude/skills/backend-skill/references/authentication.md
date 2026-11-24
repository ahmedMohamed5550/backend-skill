# Authentication Standards

## Default API Authentication
- Laravel Passport is the approved default API authentication system.
- Passport must be included in the base project dependencies.
- Prepare the project to use Passport for API authentication.
- Do not replace Passport with Sanctum or another authentication system.
- Keep roles and permissions on the `api` guard: set `auth.defaults.guard` to `api`, define the Passport `api` driver guard in `config/auth.php`, and create all Spatie roles and permissions with `guard_name` `api` (the seeder should read the guard from `config('auth.defaults.guard')`).
- The scaffold applies this configuration automatically: it installs the approved `templates/config/auth.php` over the Laravel skeleton file (default guard `api`; `api` guard with the `passport` driver on the `users` provider). Do not restore the skeleton's session-only configuration or the `web` default guard.

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
5. `php artisan db:seed` — after the project's seeders (Roles and Permissions Seeder and `PassportClientSeeder`) are registered in `DatabaseSeeder`.

The personal access client is created by seeding, not by an artisan command:

- The approved `PassportClientSeeder` template (`templates/database/seeders/PassportClientSeeder.php`) is copied into the project by the scaffold and must be registered in `DatabaseSeeder` together with the other project seeders.
- It is the single authoritative creator of the personal access client in every database (main and test): because it runs with every `db:seed`, and the test suite re-seeds on every refresh (`tests/TestCase.php` with `RefreshDatabase` and `public $seed = true`), login-dependent tests always find the client. Do not run `passport:client --personal` in project setup — bypassing the seeder hides a broken client definition until the isolated test database exposes it.
- Passport 13 `oauth_clients` column contract: `redirect_uris` and `grant_types` are JSON-cast arrays, `secret` is nullable (null means a public client), `provider` is nullable, and `revoked` is required. The old `redirect` and `public` columns no longer exist; a seeder that writes them fails with "Unknown column". Client resolution through `ClientRepository::personalAccessClient()` requires `revoked = false`, `provider` null or matching `auth.guards.api.provider`, and `grant_types` containing `personal_access`.

Additional rules:

- `scripts/scaffold.sh` implements this sequence automatically (migrations, keys, conditional seeding).
- The authentication identity is the single `App\Models\V1\User` model: the scaffold installs it from the approved template (UUID hook, Passport `HasApiTokens`, Spatie `HasRoles`), removes the skeleton's default `app/Models/User.php` and `database/factories/UserFactory.php`, and installs `config/auth.php` from the approved template so the users provider resolves `App\Models\V1\User`. The Passport `api` guard, Spatie's user resolution, seeders, and factories must all resolve users through that provider — never create or reference `App\Models\User`.
- Issue tokens with the V1 User model's `HasApiTokens` trait (`$user->createToken(...)`); do not rely on the Passport password grant.
- When publishing package configs, verify the actual publish tag against the package provider. Known mismatch: `l5-swagger` has no `l5-swagger-config` tag — copy `vendor/darkaonline/l5-swagger/config/l5-swagger.php` to `config/l5-swagger.php` directly.
- On Laravel 13 the skeleton has no `app/Http/Middleware/` directory; create it before copying the approved `SetLocale` template.

## Authentication Route Middleware

Authentication middleware is applied at the route level, never inside Controller constructors:

- Every protected module route file applies the `api` guard on its route group: `Route::middleware('auth:api')->controller({{Module}}Controller::class)->prefix('{{module}}')->group(...)`. Never apply `$this->middleware('auth:api')` in any Controller constructor (including `AuthController`).
- Authentication-only endpoints (`login`, `logout`, `me`) are gated by Passport authentication middleware, not by permission middleware. In the Auth module route file, `login` stays public and `logout` and `me` are registered inside an `auth:api` middleware group:

        Route::controller(AuthController::class)
            ->prefix('auth')
            ->group(function () {
                Route::post('/login', 'login'); // the only public auth endpoint

                Route::middleware('auth:api')->group(function () {
                    Route::post('/logout', 'logout');
                    Route::get('/me', 'me');
                });
            });

- `logout` and `me` MUST reject unauthenticated requests with `401` — rendered by the central exception handler — before the controller action runs. Without the route-level `auth:api` middleware, `auth()->user()` returns `null` inside the action and the endpoint fails with `500` instead of `401`.
- Do not attach `permission:` middleware to authentication-only endpoints: any authenticated user may log out or retrieve their own profile. Permission middleware stays per-action in the Controllers of the business modules (see the Mandatory Authorization rules).