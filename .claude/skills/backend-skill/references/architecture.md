# Backend Architecture Standards

## General
- Every project must follow the same approved backend architecture.
- Do not invent or introduce alternative architectural patterns.
- All application implementation must follow API Version 1.
- Keep the architecture simple and consistent.
- Do not add unnecessary abstraction or overengineering.

## Versioning
- All API implementation files must belong to Version 1.
- Use the V1 namespace and directory structure consistently.

## Directory Structure and Namespaces

### Controllers
- Directory: app/Http/Controllers/V1
- Namespace: App\Http\Controllers\V1
- Controllers must be placed directly inside the V1 directory, not in module-specific subdirectories or subnamespaces.

### Requests
- Directory: app/Http/Requests/V1/{Module}
- Namespace: App\Http\Requests\V1\{Module}

### Resources
- Directory: app/Http/Resources/V1/{Module}
- Namespace: App\Http\Resources\V1\{Module}

### Services
- Directory: app/Services/V1/{Module}
- Namespace: App\Services\V1\{Module}

### Models
- Directory: app/Models/V1
- Namespace: App\Models\V1
- Models must not be placed inside module-specific subdirectories.

## Module Structure
For each module:
- Create exactly one Controller.
- Create exactly one Service.
- Create exactly two API Resources:
  - List Resource
  - Show Resource
- Create Form Requests only when input validation is required.
- Do not create additional Controllers or Services for the same module.
- Do not split a module into extra classes or layers unless explicitly approved.

## Responsibilities
- Controllers handle incoming HTTP requests and delegate operations to the module Service.
- Services contain business logic and database query operations.
- Form Requests handle input validation when required.
- Resources format API response data.
- Models represent database entities and their relationships.

## Routes
- Main API routes file: routes/api1.php
- routes/api1.php must be the only skill-generated route file directly inside routes/.
- Each module must have its own route file inside routes/v1/, including the Auth and User modules.
- Laravel's own routes/web.php and routes/console.php skeleton files stay unchanged.
- The main api1.php file must require every generated routes/v1/ route file.
- Use Laravel Route controller groups with controller and prefix where applicable.
- Keep route definitions organized by module.
- Do not create route subdirectories other than routes/v1/; module route files sit directly inside routes/v1/.

## Database Files
Use Laravel's default locations:
- Migrations: database/migrations
- Seeders: database/seeders
- Factories: database/factories

## Restrictions
- Do not create a Base Model.
- Do not use SoftDeletes.
- Do not introduce Repository patterns.
- Do not introduce additional architectural layers.
- Do not move Models into module-specific directories.
- Do not change the approved namespace structure.

## Service Injection in Controllers

- Inject the module Service through the Controller constructor.
- Do not use PHP Constructor Property Promotion.
- Declare the Service as an explicit Controller property.
- The property name must match the Service class name.
- Assign the injected Service to the property inside the constructor.
- Always access the Service using its explicit property name, not a generic name such as `$service`.

Example:

```php
protected CategoryService $CategoryService;

public function __construct(CategoryService $CategoryService)
{
    $this->CategoryService = $CategoryService;
}

// Usage:
$categories = $this->CategoryService->index();
```

## Roles and Permissions System

- Every new backend project must use `spatie/laravel-permission` as its mandatory authorization and access-control system.
- Roles and permissions must be implemented from the beginning of every project.
- Do not build a custom roles or permissions system.
- Every protected Controller action must have an associated permission.
- Permission names must follow a consistent naming convention based on the module and action.
- Examples:
  - `categories-list`
  - `categories-show`
  - `categories-create`
  - `categories-update`
  - `categories-delete`
- Every Controller must register the appropriate permission middleware in its constructor, mapping permissions to their corresponding methods using the `only` option.
- Spatie's `role`, `permission`, and `role_or_permission` middleware aliases are registered by the approved `bootstrap/app.php` template. Register permission middleware using the alias form: `'permission:{permission-name}'` (equivalently `PermissionMiddleware::class.':{permission-name}'`).
- Authentication middleware (`auth:api`) is applied at the module route group level in the module's route file (`Route::middleware('auth:api')->controller(...)->prefix(...)->group(...)`), never with `$this->middleware('auth:api')` inside a Controller constructor. Controllers register only their per-action permission middleware (see references/authentication.md, Authentication Route Middleware).
- A Controller action must not be left without authorization middleware unless it is explicitly defined as a public endpoint.
- Public authentication endpoints, such as login and registration, must remain accessible without authenticated permissions.
- Generate and register permissions for all created module actions.
- Do not assign permissions to ordinary users automatically. Their roles and permissions are managed through the authorization management module.


## Role and Permission Management Module

- Every project must include a dedicated Role and Permission Management module.
- This module allows the Admin to manage application roles and permissions.
- Implement the module using the existing project architecture and standards:
  - One Controller.
  - One Service.
  - List Resource and Show Resource.
  - Form Requests when validation is required.
  - Dedicated module routes.
- The Admin must be able to:
  - List, create, show, update, and delete roles.
  - List available permissions.
  - Assign permissions to roles.
  - Remove permissions from roles.
  - View the permissions assigned to each role.
  - Assign roles to users and remove roles from users.
- Use Spatie Laravel Permission APIs for all role and permission operations.
- Do not create a custom authorization implementation.
- Protect all management endpoints using appropriate permissions.
- Only authorized Admin users can manage roles and permissions.
- Prevent accidental removal of the Admin's full access.
- The Admin role must retain all available permissions.
- Changes to roles and permissions must take effect immediately, including clearing the Spatie permission cache when necessary.

## Package Models Requiring Standard Columns

Some packages own their own database tables and Models (for example, Spatie Laravel Permission's `roles`, `permissions`, `model_has_permissions`, `model_has_roles`, and `role_has_permissions` tables). Those tables must still follow the skill's standard columns.

- When a package table must carry a standard column such as the unique `uuid`, extend the package Model into `App\Models\V1\{Model}` and add the standard `boot()` UUID hook there. The extended Model must inherit all package behavior.
- Point the package configuration at the extended Models — for example, `config/permission.php` `models.role` and `models.permission` must reference `App\Models\V1\Role` and `App\Models\V1\Permission`. The scaffold does this automatically: it installs both models from the approved templates (`templates/app/Models/V1/Role.php` and `templates/app/Models/V1/Permission.php`) and rewrites the published `config/permission.php` imports through `scripts/rewrite-v1-imports.php`. Never recreate these models or revert the configuration to the vendor models.
- Publish the package migrations and add the standard columns (for example, `$table->uuid('uuid')->unique()`) to the published migration.
- Never modify vendor package internals to achieve this.
- All project code — including seeders, controllers, and services — MUST import the extended `App\Models\V1` Models, never the vendor Model class. Importing the vendor Model silently skips the UUID hook and fails on insert with "Field 'uuid' doesn't have a default value".
- The same rule applies to the framework's default `users` table: add the unique `uuid` column to the users migration and keep the project's User Model in `App\Models\V1\User` with the standard UUID hook.
- The User Model exists exactly once: the scaffold installs `App\Models\V1\User` from the approved template and removes the Laravel skeleton's default `app/Models/User.php` and `database/factories/UserFactory.php`. Never recreate `App\Models\User`; all authentication, authorization, config, seeder, and factory references point at `App\Models\V1\User`.