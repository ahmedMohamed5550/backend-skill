# Database Standards

## Table Structure
Every database table must have:
- `id`: auto-incrementing primary key.
- `uuid`: unique UUID column.
- `created_at` and `updated_at`: Laravel timestamps.

Use Laravel's `$table->id()`, `$table->uuid('uuid')->unique()`, and `$table->timestamps()`.

## Naming Conventions
- Table names: plural snake_case.
- Model names: singular PascalCase.
- Column names: snake_case.
- Foreign key columns: `{related_model}_id`.
- Pivot tables: follow Laravel's conventional pivot table naming.

## UUID Generation
- See also: SKILL.md › Database Standards.
- Generate the UUID via a static `creating` callback in the Model's `boot()` method; the canonical implementation lives in `references/models.md`.
- Do not use Laravel's `HasUuids` trait.

## Columns and Constraints
- Business columns should be nullable by default unless business requirements specify otherwise.
- Required business fields must be enforced through Form Request validation.
- Technical columns and relationship constraints must retain their required database constraints.
- String columns use the default length of 255 unless business requirements require another length.
- Do not add database ENUM columns.

## Primary Keys and API Identification
- `id` is the internal auto-incrementing primary key.
- `uuid` is the unique identifier exposed through public APIs.
- Show, Update, and Delete operations must identify records using UUID, never the internal ID.
- Internal relationships and foreign keys continue to use numeric IDs.

## Indexes
- Do not add indexes by default.
- The unique UUID constraint is required.
- Add other indexes only when explicitly justified by project requirements.

## Relationships
- Define database foreign key constraints for relationships.
- Define the corresponding Eloquent relationships in Models.
- Foreign keys must use `ON DELETE CASCADE`.
- Many-to-many relationships must use a pivot table.
- Many-to-many relationships must define foreign keys for both related Models.
- Define `belongsToMany()` on both Models where appropriate.
- Include additional pivot attributes when required by business requirements.

## Delete Behavior
- Do not use SoftDeletes.
- Deleting a parent record must follow the configured `ON DELETE CASCADE` behavior.

## Database Transactions

- See also: SKILL.md › Database Transactions.
- Transactions must be implemented in the Service layer, never in Controllers.
- Controllers catch propagated exceptions, log them, and return the approved generic API error response.
- Never manually commit a transaction that is managed by `DB::transaction()`.

## Bulk Delete

- See also: SKILL.md › Delete Operations.

## Model Attributes
- Prefer `$fillable` for mass assignment.
- Do not include `id`, `uuid`, or generated/immutable fields in `$fillable`.

## Default Database Configuration

- See also: SKILL.md › Default Database Configuration.
- Database credentials must be configured through environment variables.
- Never hardcode database credentials in application code.
- The scaffold process must configure the generated project's `.env` automatically.


## Default Admin User Seeder

- The default admin user must have full access to the entire application.
- Create the default admin user on the single `App\Models\V1\User` model — never on the skeleton default `App\Models\User`, which the scaffold removes.
- Automatically assign all available permissions to the Admin role.
- If the project uses Spatie Laravel Permission, synchronize all defined permissions with the Admin role.
- Ensure the Admin role has unrestricted access to all modules and their actions, including Create, List, Show, Update, Delete, and any additional permissions.
- Ensure the default admin user is assigned the Admin role.
- When new permissions are added through future seeders, the Admin role must receive them automatically when seeders are executed again.


## Roles and Permissions Seeders

- Every project must include a Roles and Permissions Seeder using Spatie Laravel Permission.
- Automatically create the default `Admin` role.
- Automatically create all permissions required by the application's Controllers and module actions.
- Every permission must be stored using Spatie's permission system.
- Assign all existing application permissions to the Admin role.
- Assign the Admin role to the default admin user.
- When new modules or Controller actions are added, register their new permissions through the seeders.
- When seeders are executed again, synchronize the Admin role with all available permissions.
- Do not delete or overwrite other users' roles or permissions when synchronizing the Admin role.
- Clear Spatie's permission cache after modifying roles or permissions.
- Ensure all role and permission seeders are registered in DatabaseSeeder and run during initial project setup.