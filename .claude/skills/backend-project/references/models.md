# Model Standards

## Location and Namespace
- All Version 1 Models must be stored directly in `app/Models/V1`.
- Namespace: `App\Models\V1`.
- Do not create module-specific subdirectories for Models.

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

## API Identification
- Public API operations must identify records using UUID.
- Do not expose internal numeric IDs as public record identifiers.
- Use numeric IDs internally for database relationships and foreign keys.
