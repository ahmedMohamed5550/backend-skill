---
name: backend-skill
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
4. Automatically derive the project name from the user's business requirements following the Database Naming rules in the Default Database Configuration section. Never ask the user to provide or confirm the project name or database name.
5. Understand the business requirements.
6. Prepare the standardized Laravel foundation and initialize the Laravel project using `scripts/scaffold.sh` (see Execution Guidance below).
7. Analyze the business requirements and create `docs/IMPLEMENTATION_PLAN.md` following the Implementation Plan Requirement section: break the project into logical phases and ordered tasks, then start implementation immediately without presenting the plan to the user or waiting for approval.
8. Keep `docs/IMPLEMENTATION_PLAN.md` updated while implementing: mark tasks `In Progress` and `Completed` as work progresses, and add newly discovered tasks or requirements to the plan as they emerge.
9. Use the approved templates and configuration files.
10. Generate the required project files and configurations.
11. Create the project `README.md` from the approved `templates/README.md` template (the scaffold copies it automatically). Replace the `{{PROJECT_DESCRIPTION}}` placeholder using only the user's stated business requirements; never invent business details for it.
12. Run Composer installation commands to install all approved dependencies. Never skip Composer installation unless an actual environment blocker prevents it.
13. Configure the project according to the approved standards.
14. Automatically create the configured MySQL database if it does not exist.
15. Automatically run all required migrations and seeders.
16. Generate the database documentation file `docs/DATABASE_DOCUMENTATION.md` as required by the Database Documentation Requirement section.
17. Follow `references/testing.md` to implement the testing phase: prepare the isolated test environment, create the Feature and Unit Tests for the implemented modules and business logic, and run the complete test suite until all tests pass.
18. Verify the generated project and report any issues, including verifying that the database documentation file exists and accurately represents the actual database schema, and that every task in `docs/IMPLEMENTATION_PLAN.md` is implemented, tested, and marked `Completed`.
19. Run the Project Completion Gate as the final workflow step (see the Project Completion Gate section): run `scripts/completion-gate.sh <project-folder-name>`, confirm every gate criterion passes, and report the project as `READY` only then. If anything is incomplete or failing, the project remains `BLOCKED`: list the failing criteria, and never hand it off to the Frontend Team or other teams.

If an essential requirement is unclear, ask the user before making assumptions.

Do not start business feature implementation before the standardized foundation is ready.

### Execution Guidance

- Use `scripts/scaffold.sh <project-folder-name> [database-name]` to perform the complete foundation setup: Laravel skeleton creation, approved template application, authentication and authorization configuration, `.env` configuration, Composer installation, MySQL database creation, package migration publishing, migrations, Passport preparation, and conditional seeding. The script implements the command order documented in `references/authentication.md`.
- Create `docs/IMPLEMENTATION_PLAN.md` as soon as the scaffold has created the project skeleton. The scaffold fails if the project folder already exists, so the plan cannot be written before it runs: create the `docs/` directory if it does not exist, then write the plan before creating any application files.
- Run the scaffold script (or any long Composer command) in the background; Composer may take 15+ minutes on constrained machines.
- Never run `php artisan serve` or any artisan command in a freshly scaffolded project while the scaffold's Composer step is still running: the skeleton has no `vendor/` directory until Composer install finishes, so artisan fails with "Failed opening required .../vendor/autoload.php". Wait for the scaffold to print `Scaffold complete` (or the Composer command to exit) before running the app or any artisan command, and tell the user to do the same.
- While Composer resolves, only create complete, syntactically valid application files. Do not wire `routes/api1.php` to module routes until Composer has finished.
- Never run `passport:install` on a database that already contains the oauth tables; it fails because it re-runs migrations. Use the approved Passport 13 sequence.
- The script seeds the database only after the Roles and Permissions Seeder is registered in `DatabaseSeeder`. When seeding was skipped, run `php artisan db:seed` after implementing the module seeders.
- After any `composer.json` change that affects autoloading (PSR-4 mappings, classmaps, or `files` entries), run `composer dump-autoload` before running tests or any command that depends on the new classes. Never assume edited or newly created classes are autoloaded.
- For autoload-only regeneration, use `composer dump-autoload --no-scripts`; it skips the `post-autoload-dump` hooks such as `artisan package:discover`. Run `php artisan package:discover` separately when packages were added, updated, or removed.
- Do not pipe Composer output through commands such as `tail` or `head`: their buffering hides progress and hangs, and the pipe can mask Composer's real exit code. Read Composer's output directly, treat any non-zero exit code as a failure, and verify the actual result (for example, that a new test namespace resolves) before reporting the step as successful.

## 3. Implementation Plan Requirement

- MUST generate `docs/IMPLEMENTATION_PLAN.md` for every backend project.
- Generate the plan dynamically from the project's actual business requirements. Do not use a fixed list of modules or a predetermined task list.
- Break the project into logical phases and tasks, considering dependencies and implementation order.
- Include every relevant activity the project actually requires, such as project setup, database, models, APIs, authentication, authorization, testing, and documentation.
- Every task must have a clear description and a status: `Pending`, `In Progress`, or `Completed`.
- The plan describes WHAT must be done and in WHAT order. This skill and its references define HOW to implement it.
- After generating the plan, start implementation immediately. Never present the plan to the user or wait for its approval, and do not ask additional questions just to approve the plan.
- Follow the plan during implementation and update task statuses as work progresses.
- If additional tasks or requirements emerge during implementation, add them to the plan and continue implementation.
- Before completing the project, verify that all planned tasks have been implemented and tested.
- Do not consider backend project generation complete until every task in `docs/IMPLEMENTATION_PLAN.md` is implemented, tested, and marked `Completed`.
- The plan MUST include a final `Completion Gate Verification` task that runs the Project Completion Gate (`scripts/completion-gate.sh`); mark it `Completed` only when the project passes the gate and is `READY`.

## 4. Technology Stack

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

## 5. Workspace and Project Location

The skill workspace contains the skill configuration, references, templates, and scripts.

New backend projects must be created inside the configured Projects Workspace.

The new project must be created alongside the skill workspace, not inside the skill directory.

Always load and use the paths defined by:

`scripts/config.sh`

Do not hardcode workspace paths when the configuration script provides them.

Never create a new project inside the skill workspace.

## 6. Project Architecture

All application implementation must follow API Version 1.

### Directory Structure

- Controllers: `App\Http\Controllers\V1`
- Requests: `App\Http\Requests\V1\{Module}`
- Resources: `App\Http\Resources\V1\{Module}`
- Services: `App\Services\V1\{Module}`
- Models: `App\Models\V1`
- Enums: `App\Enums`
- Traits: `App\Traits`
- Helpers: `App\Helpers`

Controllers are stored directly inside `App\Http\Controllers\V1` and must not use module-specific subnamespaces.

Models are stored directly inside `App\Models\V1`, not in module-specific folders.

Every project must have exactly one User model: `App\Models\V1\User`, installed by the scaffold from the approved template as the single source of truth for authentication, authorization, seeders, and factories. The Laravel skeleton's default `app/Models/User.php` and default `database/factories/UserFactory.php` are removed by the scaffold and must never be recreated; all references point to `App\Models\V1\User`.

### Module Rules

Each module must have:

- Exactly one Controller.
- Exactly one Service.
- Separate Form Request classes when input validation is required.
- Exactly two Resources:
  - List Resource.
  - Show Resource.

Do not create additional Controllers or Services for the same module unless explicitly authorized.

The Documentation module is the approved exception: one public `DocumentationController` with no Service and no Resources (see API and Swagger Documentation Requirement).

The Controller delegates business operations to its module Service.

Business logic and database query operations belong in the Service, not the Controller.

Do not create Base Models, Repositories, or additional architectural layers.

Follow `references/architecture.md` for the complete architecture rules.

## 7. Routes

- Use `routes/api1.php` as the main API route file.
- Configure the API prefix as `api/v1`.
- `routes/api1.php` must be the only skill-generated route file directly inside `routes/`.
- Every module route file must be generated inside `routes/v1/`, including the Auth and User module route files.
- The main API route file must require every generated `routes/v1/` route file.
- Laravel's own `routes/web.php` and `routes/console.php` skeleton files stay unchanged; no other route file may be created directly inside `routes/`.
- Use `Route::middleware('auth:api')->controller()->prefix()->group()` for protected module routes: authentication middleware is applied at the module route group level in the module's route file, never inside a Controller constructor. Controllers keep registering their per-action permission middleware.
- Public endpoints are the only routes outside `auth:api` groups: the documentation group and the auth module's `login` route (`logout` and `me` stay inside the auth module's `auth:api` group).
- The module prefix must be lowercase and defined once.
- Additional module routes must use action paths inside the existing prefix group.
- Define static action paths (such as `/bulk-delete`) before `{uuid}` routes so the parameter cannot shadow them.
- Do not create route subdirectories other than `routes/v1/`; module route files sit directly inside `routes/v1/`, never in module-specific subdirectories.
- Every project also includes a public `routes/v1/documentation.php` route file required from `api1.php`; it is the only route group without authentication middleware (see API and Swagger Documentation Requirement).

### Standard Module Endpoints

Each module follows these default endpoints:

- GET `/module` — List.
- GET `/module/{uuid}` — Show.
- POST `/module` — Create.
- PUT `/module/{uuid}` — Update.
- DELETE `/module/{uuid}` — Single Delete.
- DELETE `/module/bulk-delete` — Bulk Delete.

Additional endpoints depend on business requirements.

Follow the approved module route template `templates/routes/module-routes.php` instead of creating a different route structure.

## 8. Database Standards

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

## 9. Default Database Configuration

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

## 10. Database Transactions

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

## 11. Delete Operations

Every module must support:

- Single delete by UUID.
- Bulk delete using a collection of UUIDs.

Before executing a delete operation, verify that the target records exist.

Bulk delete must be all-or-nothing:

- If any UUID does not exist, fail the entire operation.
- Never partially delete records.
- Use a database transaction.

Do not implement Bulk Update.

## 12. Requests and Resources

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

## 13. PHP Enums

- Use PHP Backed Enums.
- Store enum values in the database as regular strings.
- Store all enums under `app/Enums`.
- Use namespace `App\Enums`.
- Use one file per enum.
- Follow the approved enum example template `templates/app/Enums/ExampleEnum.php`.
- Use enums in Request validation.
- Use enum casting in Models.
- Do not use database ENUM column types.

## 14. API Responses

Every API response must use `ApiResponseTrait` located at:

`app/Traits/ApiResponseTrait.php`

The trait provides the `apiResponse()` method. Use the same response method for all API responses — successful operations, failed operations, validation errors, exceptions, and any other API response.

Never return a different JSON response structure.

The approved response structure and its field rules are specified in `references/api-standards.md` (API Response Structure).

Exceptions and validation errors must be handled centrally and converted to the same response structure.

Use the approved ApiResponseTrait template without silently changing its implementation.

## 15. List Operations

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

## 16. Helpers and Traits

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

## 17. Localization

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

## 18. Authentication

- Laravel Passport is the default API authentication system.
- Apply the Passport authentication middleware at the route level (`auth:api` on the module route group); never inside Controller constructors (see Routes and `references/authentication.md`, Authentication Route Middleware).
- Prepare the standard authentication foundation following the Passport 13 setup sequence in `references/authentication.md`; never run `passport:install` on a database that already contains the oauth tables.
- Google Login is an optional feature.
- Phone OTP verification is an optional feature.
- Twilio is the default provider for phone OTP.
- Authentication and verification flows must integrate with the single V1 User Model (`App\Models\V1\User`) and the users table.
- Store OTP information securely; never store OTP values in plain text.
- Keep provider credentials in environment variables.

Do not enable optional authentication methods unless required by the project.

Follow `references/authentication.md`.

### Mandatory Authorization

Every project MUST use Spatie Laravel Permission for roles and permissions.

The scaffold installs the extended `App\Models\V1\Role` and `App\Models\V1\Permission` models from the approved templates and points the published `config/permission.php` at them. Never recreate these models or point the configuration back at the vendor models; use the V1 models for all role and permission operations.

Every protected Controller action must have its required permission middleware.

Follow `references/authentication.md` for the complete authorization implementation, permission naming, seeders, and Role and Permission Management module.

## 19. API and Swagger Documentation Requirement

### Swagger/OpenAPI Documentation

- Swagger documentation is part of the backend project setup.
- Use the approved Swagger documentation reference: `references/swagger.md`.
- Follow the Swagger reference when creating and documenting API endpoints.

### API Documentation

- Reading `references/api-documentation.md` is mandatory when generating or modifying any backend project.
- Follow all instructions in that reference when generating API documentation.
- After completing the backend API implementation, MUST generate `docs/API_DOCUMENTATION.md`.
- The generated file MUST cover every implemented module, endpoint, CRUD operation, and additional business action.
- The documentation MUST be based on the actual implementation, not assumptions or initial requirements alone.
- MUST generate and maintain both Swagger/OpenAPI documentation and `docs/API_DOCUMENTATION.md`.
- Both documentation outputs MUST remain consistent with the actual API implementation.
- Whenever an endpoint or its behavior changes, update both documentation outputs.
- Do not consider backend generation complete until the API documentation has been generated and verified.
- Every project MUST expose a public documentation endpoint by default: `GET /api/v1/documentation`, implemented by `App\Http\Controllers\V1\DocumentationController` and the `routes/v1/documentation.php` route file (copied automatically by the scaffold).
- The endpoint MUST require no authentication and no permission. It returns `docs/API_DOCUMENTATION.md` with `Content-Type: text/markdown`.
- Frontend and mobile clients always receive two documentation links: the Swagger UI and the public documentation endpoint.
- If `docs/API_DOCUMENTATION.md` has not been generated yet, the endpoint returns the standard 404 API response shape.
- The Documentation module is the approved exception to the module structure: exactly one Controller, no Service, and no Resources, because it serves a file and contains no business logic.

## 20. Database Documentation Requirement

- MUST generate `docs/DATABASE_DOCUMENTATION.md` for every backend project.
- `docs/DATABASE_DOCUMENTATION.md` MUST provide concise documentation for every database table, including:
  - Table purpose.
  - Important columns and their descriptions.
  - Relationships with other tables.
  - Relevant business rules affecting the table.
- Documentation MUST be based on the actual implemented database schema, migrations, models, and relationships.
- Do not document tables, columns, or relationships that do not exist in the actual implementation.
- Keep the documentation concise and practical, focusing on helping backend developers quickly understand the database structure.
- MUST update the file whenever database schema or relationships change.
- Do not consider backend project generation complete until the file has been generated and verified.

## 21. Laravel Configuration

Use the approved Laravel configuration templates.

- Use the approved `bootstrap/app.php` template.
- Use the approved `bootstrap/providers.php` template.
- Use the approved `config/app.php` template.
- Use the approved `config/auth.php` template. It sets the default authentication guard to `api` and defines the `api` guard with the `passport` driver on the `users` provider; the scaffold installs it over the Laravel skeleton file.
- The scaffold points the Spatie-published `config/permission.php` at the extended `App\Models\V1\Permission` and `App\Models\V1\Role` models (see Mandatory Authorization) and installs both models from the approved templates. Do not change these references to the vendor models.
- Preserve Laravel's default required files unless a specific approved template replaces them.
- Do not add custom Service Providers or aliases unless explicitly approved.
- Register the approved API middleware and exception configuration through the Laravel 13 bootstrap configuration.

Do not silently replace or redesign approved configuration templates.

## 22. Composer Configuration

Use the approved `templates/composer.json` as the source of truth for Composer dependencies and configuration.

- Preserve the approved PHP and Laravel version constraints.
- Preserve the approved production and development dependencies.
- Preserve the approved PSR-4 autoload mappings.
- Register only the approved Helpers file under `autoload.files`.
- Do not independently remove, replace, or add dependencies without user approval.
- Do not rely on Composer scripts alone for database creation. The project setup workflow must   automatically create the configured MySQL database if it does not exist, then run the required migrations without asking for user approval.
- Ensure Composer configuration remains valid.

Follow `references/dependencies.md`.

## 23. Mandatory References

The following reference files are mandatory and must be read and followed when creating a project:

| Reference | Covers | Primary use when |
|---|---|---|
| `references/architecture.md` | Project structure, namespaces, module rules and responsibilities, service injection, roles and permissions architecture, package models | Implementing any module or architectural layer. |
| `references/database.md` | Migration and table implementation details, indexes, database seeders, default admin user | Creating migrations, seeders, or database configuration. |
| `references/models.md` | Model location, UUID generation implementation, mass assignment, relationships, API identification | Creating or modifying Models. |
| `references/api-standards.md` | Controllers, Services, Requests, Resources, API response structure, delete and list operations, exception handling, route and permission naming | Implementing endpoints and API behavior. |
| `references/api-documentation.md` | `docs/API_DOCUMENTATION.md` generation standard | Generating or updating API documentation. |
| `references/authentication.md` | Passport 13 setup sequence, Google Login, phone OTP, authorization and permissions implementation | Implementing authentication or permissions. |
| `references/localization.md` | Supported languages, `SetLocale` middleware | Configuring localization. |
| `references/helpers-and-traits.md` | Approved Helpers methods, `ApiResponseTrait`, excluded helper files | Using Helpers or the response trait. |
| `references/dependencies.md` | Approved Composer dependencies and autoload configuration | Modifying `composer.json` or installing packages. |
| `references/swagger.md` | L5-Swagger annotation standards | Annotating endpoints for Swagger. |
| `references/testing.md` | Test environment, Feature and Unit test standards | Implementing or running tests. |
| `references/file-storage.md` | File upload, replacement, deletion, storage consistency | Implementing file uploads or stored files. |

Do not ignore relevant reference files.

If a reference conflicts with this skill or another approved standard, explain the conflict and ask the user before proceeding.

## 24. Mandatory Templates and Configuration

Every provided template is mandatory and must be used when applicable.

Never ignore, skip, or replace an existing template with a custom implementation.

Always read and follow the relevant references and templates before implementation.

### Template Manifest

Templates the scaffold copies automatically (do not copy them manually):

- `templates/composer.json`
- `templates/bootstrap/app.php`
- `templates/bootstrap/providers.php`
- `templates/config/app.php`
- `templates/config/auth.php`
- `templates/app/Helpers/Helpers.php`
- `templates/app/Traits/ApiResponseTrait.php`
- `templates/app/Http/Middleware/SetLocale.php`
- `templates/app/Http/Controllers/Controller.php`
- `templates/lang/en/messages.php`
- `templates/lang/ar/messages.php`
- `templates/routes/api1.php`
- `templates/routes/documentation.php`
- `templates/app/Http/Controllers/V1/DocumentationController.php`
- `templates/app/Models/V1/User.php`
- `templates/app/Models/V1/Role.php`
- `templates/app/Models/V1/Permission.php`
- `templates/database/factories/V1/UserFactory.php`
- `templates/database/seeders/PassportClientSeeder.php`
- `templates/README.md`

Templates to adapt when implementing modules (not copied by the scaffold):

- `templates/routes/module-routes.php` — approved structure for each module route file (see Routes).
- `templates/app/Enums/ExampleEnum.php` — approved example for PHP Backed Enums (see PHP Enums).

If a target file already exists:

1. Inspect its content.
2. Compare it with the approved template.
3. Do not silently overwrite it.
4. Explain any conflict and ask the user before proceeding.

Never modify approved templates or standards without the user's explicit approval.

Do not consider a template optional unless the user explicitly approves that exception.

## 25. Verification

After creating the standardized foundation:

- Verify the Laravel project was created in the configured Projects Workspace.
- Verify the PHP and Laravel requirements.
- Verify Composer configuration and dependency installation.
- Verify all mandatory templates were applied.
- Verify the project has exactly one User model and exactly one UserFactory: `app/Models/V1/User.php` and `database/factories/V1/UserFactory.php` exist; the skeleton's default `app/Models/User.php` and `database/factories/UserFactory.php` are absent; the factory namespace is `Database\Factories\V1` and it is linked to `App\Models\V1\User` so `User::factory()` resolves through it; and no reference to `App\Models\User` remains.
- Verify `README.md` exists in the project root, was created from the approved template, and contains no unresolved placeholders.
- Verify `docs/DATABASE_DOCUMENTATION.md` exists, matches the actual implemented database schema, and documents no tables, columns, or relationships that do not exist.
- Verify `docs/IMPLEMENTATION_PLAN.md` exists, matches the actual implementation, and every task is implemented, tested, and marked `Completed`.
- Verify the route configuration and API prefix.
- Verify the authentication middleware placement: every protected module route group applies `auth:api` at the route level in its `routes/v1/` file, and no Controller constructor applies `$this->middleware('auth:api')`; per-action permission middleware remains in the Controllers.
- Verify that `routes/api1.php` is the only skill-generated route file directly inside `routes/`, that every module route file is generated inside `routes/v1/`, and that `api1.php` requires every generated `routes/v1/` route file.
- Verify the MySQL environment configuration.
- Verify the authentication configuration: `config/auth.php` sets the default guard to `api` and defines the `api` guard with the `passport` driver and the `users` provider.
- Verify the authorization configuration: `config/permission.php` imports and references `App\Models\V1\Role` and `App\Models\V1\Permission`, and both model files exist in `app/Models/V1`.
- Verify the approved middleware and locale configuration.
- Verify the Helpers autoload configuration.
- Verify that the approved references and architecture have been followed.
- Verify that the project can run without configuration or syntax errors.
- Verify the testing phase was completed following `references/testing.md`: the isolated test environment is configured, Feature Tests exist for the implemented modules, and the complete test suite passes.
- When the project implements file uploads or stored files, verify file operations follow `references/file-storage.md`: database references match stored paths, and no files are deleted or overwritten before their related transaction commits.
- Report any missing dependencies, environment limitations, or failed commands honestly.
- Run the Project Completion Gate (`scripts/completion-gate.sh <project-folder-name>`) as the final verification step. The project is `READY` only when every gate criterion in the Project Completion Gate section passes; otherwise it remains `BLOCKED`.
- Never hand off a `BLOCKED` project to the Frontend Team or any other team.

Do not claim that the project or an endpoint works unless it has actually been tested.

## 26. Project Completion Gate

Never consider a project created or READY just because its files and code have been generated. Generated code alone is never sufficient.

Every project has exactly two possible completion states: `READY` and `BLOCKED`. The state is determined only by the gate below.

### Gate Criteria

A project is `READY` only when ALL of the following are true:

1. All Backend Skill requirements are fully implemented.
2. Composer and all dependencies are installed successfully.
3. Database setup, migrations, seeders, and required configurations are completed.
4. All routes, authentication, and integrations work correctly.
5. Swagger and all required documentation are generated and verified.
6. The complete required test suite passes.
7. The application boots and runs successfully.

### BLOCKED State

If anything is incomplete or failing, the project MUST remain `BLOCKED`:

- Never report a `BLOCKED` project as `READY` or complete.
- Never hand off a `BLOCKED` project to the Frontend Team or any other team.
- Report the failing gate criteria explicitly, with the actual error or missing item for each one.

### Running the Gate

- Run `scripts/completion-gate.sh <project-folder-name>` as the final workflow step, after all implementation, documentation, and testing work is finished. The script verifies every machine-checkable criterion (Composer installation, configuration, migrations, routes, Swagger generation, documentation files, approved structure checks, the complete test suite, and a boot and HTTP smoke test of the public documentation endpoint) and prints the verdict: exit code 0 means `READY`, any other result means `BLOCKED` with the failure list.
- Read the failure list, fix the actual root causes, and re-run the gate until it passes. Do not weaken or skip checks to make the gate pass.
- The script does not replace judgment: criteria 1 and 4 (full requirement coverage; routes, authentication, and integrations working correctly) and documentation accuracy under criterion 5 must be confirmed through the Verification section before reporting `READY`.
- Report the final state explicitly as `READY` or `BLOCKED`. A project with no completed gate report is `BLOCKED` by default.

## 27. Important Restrictions

- Never invent business requirements.
- Never invent architecture or additional layers.
- Never create extra Controllers or Services without approval.
- Never apply authentication middleware (`auth:api`) inside a Controller constructor; apply it at the module route group level in the module's route file.
- Never use SoftDeletes.
- Never use database ENUM columns.
- Never use a different API response format.
- Never use database IDs as public API identifiers.
- Never skip transactions for database write operations.
- Never use SQLite as the default database.
- Automatically create the project's MySQL database if it does not exist, without asking for approval.
- Never skip mandatory templates or references.
- Never run tests against the main project database.
- Never delete or overwrite a stored file before its related database transaction commits.
- Never consider a project `READY`, report it complete, or hand it off to the Frontend Team or other teams while any Project Completion Gate criterion is incomplete or failing.
- Never modify approved standards without explicit approval.