<?php

namespace App\Models\V1;

use Illuminate\Support\Str;
use Spatie\Permission\Models\Role as SpatieRole;

/**
 * Extended Spatie Permission Role model carrying the standard unique uuid
 * column (references/architecture.md, Package Models Requiring Standard
 * Columns). The scaffold installs this approved template and points
 * config/permission.php models.role at it. All project code must import
 * this model, never the vendor model.
 */
class Role extends SpatieRole
{
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            $model->uuid = (string) Str::uuid();
        });
    }
}
