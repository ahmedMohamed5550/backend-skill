# Model Standards

## Location and Namespace
- All Version 1 Models must be stored directly in `app/Models/V1`.
- Namespace: `App\Models\V1`.
- Do not create module-specific subdirectories for Models.
- The User model is the single `app/Models/V1/User.php` (`App\Models\V1\User`), installed by the scaffold from the approved `templates/app/Models/V1/User.php` template with the UUID hook, Passport `HasApiTokens`, and Spatie `HasRoles`. It is the only User model: the scaffold removes the Laravel skeleton's default `app/Models/User.php` together with its default `database/factories/UserFactory.php`, and authentication, authorization, configs, seeders, and factories must never reference or recreate `App\Models\User`.
- The scaffold also installs the extended Spatie models `app/Models/V1/Role.php` (`App\Models\V1\Role`) and `app/Models/V1/Permission.php` (`App\Models\V1\Permission`) from the approved templates; each extends its vendor model and adds the standard UUID hook, and `config/permission.php` references them. Never recreate them or import the vendor model classes directly (see references/architecture.md, Package Models Requiring Standard Columns).

## UUID
- Every Model must have a UUID column in its corresponding database table.
- Generate the UUID automatically inside the Model's `boot()` method.
- Use a static `creating` callback and `(string) Str::uuid()`.
- Do not use Laravel's `HasUuids` trait.

Example:

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            $model->uuid = (string) Str::uuid();
        });
    }

## Mass Assignment
- Prefer `$fillable` over `$guarded`.
- Do not include `id`, `uuid`, or generated/immutable fields in `$fillable`.
- Include only attributes that are allowed to be mass-assigned.

## Relationships
- Define Eloquent relationships in the corresponding Models.
- Use the appropriate relationship type, such as `belongsTo`, `hasMany`, `hasOne`, or `belongsToMany`.
- Many-to-many relationships must use the approved pivot table conventions.
- Define pivot attributes when required by business requirements.

## Database Behavior
- Do not use SoftDeletes.
- Do not use the `HasUuids` trait.
- Do not create a shared Base Model.
- Use normal Eloquent Models.

## Factories
- Model factories MUST be stored in `database/factories/V1` with the namespace `Database\Factories\V1` — not in the skeleton's `database/factories` root.
- Name each factory after its model: `App\Models\V1\{Model}` maps to `Database\Factories\V1\{Model}Factory` through Laravel's `HasFactory` convention. No additional configuration is required; placing or naming the factory differently makes `Model::factory()` fail with "class not found".
- Declare the corresponding model with `protected $model = \App\Models\V1\{Model}::class;` and import it at the top of the file.
- Never set `uuid` in a factory definition: the Model's `creating` hook generates it (and the column is unique).
- The scaffold installs a `Database\Factories\V1\UserFactory` template for the V1 user model and removes the skeleton's default `Database\Factories\UserFactory`; create further model factories following the same location and namespace.
- After creating or moving factory files, run `composer dump-autoload --no-scripts` before running tests (see SKILL.md, Execution Guidance).

## API Identification
- Public API operations must identify records using UUID.
- Do not expose internal numeric IDs as public record identifiers.
- Use numeric IDs internally for database relationships and foreign keys.
