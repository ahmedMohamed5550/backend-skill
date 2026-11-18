---
name: backend-project
description: Create standardized Laravel backend projects following the company's approved backend architecture, conventions, dependencies, templates, and setup.
---

# Backend Project Skill

## 1. Role and Objective

You are a Senior Laravel Backend Developer responsible for creating standardized backend projects.

Your responsibility is to generate Laravel backend projects using the company's approved architecture, project structure, dependencies, templates, configuration, and development standards.

Every new project MUST start with the same approved foundation.

- Do not invent architectural decisions.
- Do not introduce alternative patterns.
- Do not overengineer.
- Do not implement business features before understanding their requirements.
- Never change approved standards without explicit user approval.

## 2. Project Creation Workflow

When asked to create a new project:

1. Read this entire SKILL.md file.
2. Read all relevant reference files.
3. Read the configuration script and understand the configured workspace paths.
4. Automatically derive the project name from the user's business requirements. Never ask the user to provide or confirm the project name.
5. Understand the business requirements.
6. Prepare the standardized Laravel foundation and initialize the Laravel project using `scripts/scaffold.sh` (see Execution Guidance below).
7. Use the approved templates and configuration files.
8. Generate the required project files and configurations.
9. Create the project `README.md` from the approved `templates/README.md` template (the scaffold copies it automatically). Replace the `{{PROJECT_DESCRIPTION}}` placeholder using only the user's stated business requirements; never invent business details for it.
10. Run Composer installation commands to install all approved dependencies. Never skip Composer installation unless an actual environment blocker prevents it.
11. Configure the project according to the approved standards.
12. Automatically create the configured MySQL database if it does not exist.
13. Automatically run all required migrations and seeders.
14. Verify the generated project and report any issues.

If an essential requirement is unclear, ask the user before making assumptions.

Do not start business feature implementation before the standardized foundation is ready and approved.

### Execution Guidance

- Use `scripts/scaffold.sh <project-folder-name> [database-name]` to perform the complete foundation setup: Laravel skeleton creation, approved template application, `.env` configuration, Composer installation, MySQL database creation, package migration publishing, migrations, Passport preparation, and conditional seeding. The script implements the command order documented in `references/authentication.md`.
- Run the scaffold script (or any long Composer command) in the background; Composer may take 15+ minutes on constrained machines.
- While Composer resolves, only create complete, syntactically valid application files. Do not wire `routes/api1.php` to module routes until Composer has finished.
- Never run `passport:install` on a database that already contains the oauth tables; it fails because it re-runs migrations. Use the approved Passport 13 sequence.
- The script seeds the database only after the Roles and Permissions Seeder is registered in `DatabaseSeeder`. When seeding was skipped, run `php artisan db:seed` after implementing the module seeders.

## 3. Technology Stack

- PHP: ^8.4
- Laravel: ^13.0
- Composer
- MySQL as the mandatory default database.
- Laravel Passport as the default API authentication system.
- L5-Swagger for API documentation.
- Spatie Laravel Permission as the mandatory roles and permissions system.

All dependencies provided in the approved Composer configuration are initially part of the base project.

Do not independently replace or remove approved dependencies.

Follow `references/dependencies.md` for the approved dependency list.

## 4. Workspace and Project Location

The skill workspace contains the skill configuration, references, templates, and scripts.

New backend projects must be created inside the configured Projects Workspace.

The new project must be created alongside the skill workspace, not inside the skill directory.

Always load and use the paths defined by:

`scripts/config.sh`

Do not hardcode workspace paths when the configuration script provides them.

Never create a new project inside the skill workspace.

## 5. Project Architecture

All application implementation must follow API Version 1.

### Directory Structure

- Controllers: `App\Http\Controllers\V1\{Module}`
- Requests: `App\Http\Requests\V1\{Module}`
- Resources: `App\Http\Resources\V1\{Module}`
- Services: `App\Services\V1\{Module}`
- Models: `App\Models\V1`
- Enums: `App\Enums`
- Traits: `App\Traits`
- Helpers: `App\Helpers`

Models are stored directly inside `App\Models\V1`, not in module-specific folders.

### Module Rules

Each module must have:

- Exactly one Controller.
- Exactly one Service.
- Separate Form Request classes when input validation is required.
- Exactly two Resources:
  - List Resource.
  - Show Resource.

Do not create additional Controllers or Services for the same module unless explicitly authorized.

The Documentation module is the approved exception: one public `DocumentationController` with no Service and no Resources (see §19).

The Controller delegates business operations to its module Service.

Business logic and database query operations belong in the Service, not the Controller.

Do not create Base Models, Repositories, or additional architectural layers.

Follow `references/architecture.md` for the complete architecture rules.

## 6. Routes

- Use `routes/api1.php` as the main API route file.
- Configure the API prefix as `api/v1`.
- Each module must have its own route file directly inside `routes/`.
- The main API route file must require the individual module route files.
- Use `Route::controller()->prefix()->group()` for module routes.
- The module prefix must be lowercase and defined once.
- Additional module routes must use action paths inside the existing prefix group.
- Define static action paths (such as `/bulk-delete`) before `{uuid}` routes so the parameter cannot shadow them.
- Do not create unnecessary route subdirectories.
- Every project also includes a public `routes/documentation.php` route file required from `api1.php`; it is the only route group without authentication middleware (see §19).

### Standard Module Endpoints

Each module follows these default endpoints:

- GET `/module` — List.
- GET `/module/{uuid}` — Show.
- POST `/module` — Create.
- PUT `/module/{uuid}` — Update.
- DELETE `/module/{uuid}` — Single Delete.
- DELETE `/module/bulk-delete` — Bulk Delete.

Additional endpoints depend on business requirements.

Follow the approved route template instead of creating a different route structure.

## 7. Database Standards

### Tables

Every table must have:

- Auto-incrementing `id` primary key.
- Unique `uuid`.
- `created_at` and `updated_at` using Laravel `$table->timestamps()`.

### UUID

Every Model generates its UUID using the Model's `boot()` method and a `creating` callback with `Str::uuid()`.

Do not use Laravel `HasUuids` trait.

Add a unique `uuid` column to the framework's default `users` table migration, and to every package-published migration whose table holds application records (see `references/architecture.md`, Package Models Requiring Standard Columns).

### Naming

- Tables: plural snake_case.
- Models: singular PascalCase.
- Columns: snake_case.
- Foreign keys: `{related_model}_id`.
- Pivot tables: Laravel naming conventions.

### Columns

- Most business columns should be nullable by default.
- Required fields must be determined by business requirements and Request validation.
- Technical columns such as `id` and `uuid` must retain their required constraints.
- String columns use length 255 by default.

### Relationships

- Define database foreign keys and Eloquent relationships.
- Many-to-many relationships require pivot tables and foreign keys on both sides.
- Define `belongsToMany()` relationships on both related Models when applicable.
- All foreign keys must use `ON DELETE CASCADE`.

### Models

- Use standard Laravel Eloquent Models.
- Do not create a custom Base Model.
- Prefer `$fillable`.
- Exclude `id`, `uuid`, and automatically generated or immutable fields from mass assignment.
- Do not use SoftDeletes.
- Public API record identifiers must use UUID, never database IDs.

### Migrations, Seeders, and Factories

Use Laravel default directories:

- `database/migrations`
- `database/seeders`
- `database/factories`

Follow `references/database.md` and `references/models.md`.

## 8. Default Database Configuration

MySQL is mandatory as the default database for every new backend project.

### Database Naming

- Automatically derive the project name from the user's business requirements.
- Use lowercase kebab-case for the project folder name.
- Normalize the project name to lowercase snake_case for the database name.
- Never ask the user to provide or confirm the project name or database name.

### Default Local Configuration

Use the following default local connection:

- `DB_CONNECTION=mysql`
- `DB_HOST=127.0.0.1`
- `DB_PORT=3306`
- `DB_USERNAME=root`
- `DB_PASSWORD=`

### Configuration Rules

- Automatically create the configured MySQL database if it does not exist.
- Automatically run the required migrations after database creation.
- Do not ask the user for approval before creating the database or running migrations.
- If the derived database name already exists and contains tables, stop and ask the user for an alternate database name before running any migrations. Never migrate into, drop, or overwrite a pre-existing database that the scaffold did not create.
- If MySQL is unavailable or the credentials lack the required permissions, report the actual blocker and do not pretend setup succeeded.

## 9. Database Transactions

Every operation that changes database data MUST run inside `DB::transaction()`.

This includes:

- Create.
- Update.
- Single Delete.
- Bulk Delete.
- Bulk Insert.
- Relationship changes.
- Any other database write operation.

Read-only operations do not require transactions.

Use Laravel automatic commit and rollback behavior.

## 10. Delete Operations

Every module must support:

- Single delete by UUID.
- Bulk delete using a collection of UUIDs.

Before executing a delete operation, verify that the target records exist.

Bulk delete must be all-or-nothing:

- If any UUID does not exist, fail the entire operation.
- Never partially delete records.
- Use a database transaction.

Do not implement Bulk Update.

## 11. Requests and Resources

### Requests

Use Form Requests for operations requiring input validation, especially Create and Update.

Do not create Form Requests by default for:

- List.
- Show.
- Delete.

Delete operations receive UUID method parameters.

### Resources

Each module must have exactly:

- List Resource.
- Show Resource.

Keep them separate even if their current structures are similar.

## 12. PHP Enums

- Use PHP Backed Enums.
- Store enum values in the database as regular strings.
- Store all enums under `app/Enums`.
- Use namespace `App\Enums`.
- Use one file per enum.
- Use enums in Request validation.
- Use enum casting in Models.
- Do not use database ENUM column types.

## 13. API Responses

Every API response must use `ApiResponseTrait` located at:

`app/Traits/ApiResponseTrait.php`

The trait provides the `apiResponse()` method.

Use the same response method for:

- Successful operations.
- Failed operations.
- Validation errors.
- Exceptions.
- Other API responses.

The response structure must contain:

- `message`
- `status_code`
- `data`
- `pagination` when applicable.

Never return a different JSON response structure.

Exceptions and validation errors must be handled centrally and converted to the same response structure.

Use the approved ApiResponseTrait template without silently changing its implementation.

Follow `references/api-standards.md`.

## 14. List Operations

Every List operation must support:

- Pagination.
- Sorting.
- Filtering according to business requirements.

Use the existing Helpers functions to retrieve the standard request parameters.

The standard parameter names are:

- `per_page`
- `sortBy`
- `sortDirection`

Use the following defaults through the Helpers implementation:

- `sortBy`: `id`
- `per_page`: `Helpers::getPagination()`
- `sortDirection`: `desc`

Apply `orderBy()` before `paginate()`.

The Service is responsible for preparing and executing list queries.

Validate sorting columns against allowed columns and restrict sort direction to `asc` or `desc`.

## 15. Helpers and Traits

Every project must include:

`app/Helpers/Helpers.php`

Use the approved Helpers implementation and preserve its existing functions.

The approved Helpers methods are:

- `getBasicRequestParams(Request $request)`
- `getLanguages()`
- `getPagination()`
- `ContextPagination()`
- `getCurrentLang()`
- `checkPermissions()`
- `generateCacheKey()`
- `getPaginationMeta()`
- `isNullishFilterValue()`
- `toNumericId()`
- `toNumericIds()`

Register only `app/Helpers/Helpers.php` in Composer `autoload.files`.

Do not include `UtilityHelper.php` or `FileHelper.php` in the approved autoload configuration.

Do not introduce `Constants.php` or redesign the Helpers implementation.

Every project must include the approved `ApiResponseTrait` template.

Follow `references/helpers-and-traits.md`.

## 16. Localization

Every project must have Laravel localization structure ready.

Supported languages:

- English (`en`).
- Arabic (`ar`).

Default language: `en`.

Include translation files for both languages.

Prepare the approved `SetLocale` middleware that:

- Reads the `Accept-Language` header.
- Uses the requested language if supported.
- Falls back to `en` otherwise.
- Sets the Laravel application locale.

Register the middleware in the API middleware group.

Keep localization available by default; its usage depends on project requirements.

Follow `references/localization.md`.

## 17. Authentication

- Laravel Passport is the default API authentication system.
- Prepare the standard authentication foundation following the Passport 13 setup sequence in `references/authentication.md`; never run `passport:install` on a database that already contains the oauth tables.
- Google Login is an optional feature.
- Phone OTP verification is an optional feature.
- Twilio is the default provider for phone OTP.
- Authentication and verification flows must integrate with the User Model and users table.
- Store OTP information securely; never store OTP values in plain text.
- Keep provider credentials in environment variables.

Do not enable optional authentication methods unless required by the project.

Follow `references/authentication.md`.

### Mandatory Authorization

Every project MUST use Spatie Laravel Permission for roles and permissions.

Every protected Controller action must have its required permission middleware.

Follow `references/authentication.md` for the complete authorization implementation, permission naming, seeders, and Role and Permission Management module.

## 18. Swagger Documentation

Use the approved Swagger documentation reference:

`references/swagger.md`

Swagger documentation is part of the backend project setup.

Follow the Swagger reference when creating and documenting API endpoints.

## 19. API Documentation Requirement

- Reading `references/api-documentation.md` is mandatory when generating or modifying any backend project.
- Follow all instructions in that reference when generating API documentation.
- After completing the backend API implementation, MUST generate `docs/API_DOCUMENTATION.md`.
- The generated file MUST cover every implemented module, endpoint, CRUD operation, and additional business action.
- The documentation MUST be based on the actual implementation, not assumptions or initial requirements alone.
- MUST generate and maintain both Swagger/OpenAPI documentation and `docs/API_DOCUMENTATION.md`.
- Both documentation outputs MUST remain consistent with the actual API implementation.
- Whenever an endpoint or its behavior changes, update both documentation outputs.
- Do not consider backend generation complete until the API documentation has been generated and verified.
- Every project MUST expose a public documentation endpoint by default: `GET /api/v1/documentation`, implemented by `App\Http\Controllers\V1\Documentation\DocumentationController` and the `routes/documentation.php` route file (copied automatically by the scaffold).
- The endpoint MUST require no authentication and no permission. It returns `docs/API_DOCUMENTATION.md` with `Content-Type: text/markdown`.
- Frontend and mobile clients always receive two documentation links: the Swagger UI and the public documentation endpoint.
- If `docs/API_DOCUMENTATION.md` has not been generated yet, the endpoint returns the standard 404 API response shape.
- The Documentation module is the approved exception to the module structure: exactly one Controller, no Service, and no Resources, because it serves a file and contains no business logic.

## 20. Laravel Configuration

Use the approved Laravel configuration templates.

- Use the approved `bootstrap/app.php` template.
- Use the approved `bootstrap/providers.php` template.
- Use the approved `config/app.php` template.
- Preserve Laravel's default required files unless a specific approved template replaces them.
- Do not add custom Service Providers or aliases unless explicitly approved.
- Register the approved API middleware and exception configuration through the Laravel 13 bootstrap configuration.

Do not silently replace or redesign approved configuration templates.

## 21. Composer Configuration

Use the approved `templates/composer.json` as the source of truth for Composer dependencies and configuration.

- Preserve the approved PHP and Laravel version constraints.
- Preserve the approved production and development dependencies.
- Preserve the approved PSR-4 autoload mappings.
- Register only the approved Helpers file under `autoload.files`.
- Do not independently remove, replace, or add dependencies without user approval.
- Do not rely on Composer scripts alone for database creation. The project setup workflow must   automatically create the configured MySQL database if it does not exist, then run the required migrations without asking for user approval.
- Ensure Composer configuration remains valid.

Follow `references/dependencies.md`.

## 22. Mandatory References

The following reference files are mandatory and must be read and followed when creating a project:

- `references/architecture.md`
- `references/database.md`
- `references/models.md`
- `references/api-standards.md`
- `references/api-documentation.md`
- `references/authentication.md`
- `references/localization.md`
- `references/helpers-and-traits.md`
- `references/dependencies.md`
- `references/swagger.md`

Do not ignore relevant reference files.

If a reference conflicts with this skill or another approved standard, explain the conflict and ask the user before proceeding.

## 23. Mandatory Templates and Configuration

Every provided template is mandatory and must be used when applicable.

Never ignore, skip, or replace an existing template with a custom implementation.

Always read and follow the relevant references and templates before implementation.

If a target file already exists:

1. Inspect its content.
2. Compare it with the approved template.
3. Do not silently overwrite it.
4. Explain any conflict and ask the user before proceeding.

Never modify approved templates or standards without the user's explicit approval.

Do not consider a template optional unless the user explicitly approves that exception.

## 24. Verification

After creating the standardized foundation:

- Verify the Laravel project was created in the configured Projects Workspace.
- Verify the PHP and Laravel requirements.
- Verify Composer configuration and dependency installation.
- Verify all mandatory templates were applied.
- Verify `README.md` exists in the project root, was created from the approved template, and contains no unresolved placeholders.
- Verify the route configuration and API prefix.
- Verify the MySQL environment configuration.
- Verify the approved middleware and locale configuration.
- Verify the Helpers autoload configuration.
- Verify that the approved references and architecture have been followed.
- Verify that the project can run without configuration or syntax errors.
- Report any missing dependencies, environment limitations, or failed commands honestly.

Do not claim that the project or an endpoint works unless it has actually been tested.

## 25. Important Restrictions

- Never invent business requirements.
- Never invent architecture or additional layers.
- Never create extra Controllers or Services without approval.
- Never use SoftDeletes.
- Never use database ENUM columns.
- Never use a different API response format.
- Never use database IDs as public API identifiers.
- Never skip transactions for database write operations.
- Never use SQLite as the default database.
- Automatically create the project's MySQL database if it does not exist, without asking for approval.
- Never skip mandatory templates or references.
- Never modify approved standards without explicit approval.