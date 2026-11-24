#!/usr/bin/env bash
set -euo pipefail

# Backend Project Scaffold Script
#
# Creates a standardized Laravel backend project following SKILL.md section 2.
# The command order encodes the verified Laravel 13 / Passport 13 / Spatie v6
# setup sequence documented in references/authentication.md, including:
#   - Laravel 13 skeleton has no app/Http/Middleware directory (created here).
#   - Passport 13 publishes migrations instead of auto-loading them.
#   - The telescope publish can re-copy Passport oauth migrations (deduped here).
#   - passport:install is never used (it re-runs migrations and fails on
#     existing oauth tables); passport:keys + the PassportClientSeeder
#     template (registered in DatabaseSeeder) provide keys and the personal
#     access client instead.
#   - Authentication defaults come from templates/config/auth.php: the
#     default guard is `api` and the `api` guard uses the `passport` driver
#     with the `users` provider (references/authentication.md).
#   - Authorization config comes from templates/app/Models/V1/{Role,
#     Permission}.php plus rewrite-v1-imports.php pointing
#     config/permission.php models.role / models.permission at
#     App\Models\V1\Role / App\Models\V1\Permission
#     (references/architecture.md, Package Models Requiring Standard Columns).
#
# Usage:
#   bash scaffold.sh <project-folder-name> [database-name]
#
#   <project-folder-name>  lowercase kebab-case project folder name.
#   [database-name]        optional; defaults to the project name converted to
#                          lowercase snake_case.
#
# Workspace paths come from scripts/config.sh. Run this script in the
# background: Composer install.

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=config.sh
source "$SCRIPT_DIR/config.sh"

TEMPLATES_DIR="$SKILL_DIR/templates"

PROJECT_NAME="${1:?Usage: scaffold.sh <project-folder-name> [database-name]}"
DB_NAME="${2:-$(echo "$PROJECT_NAME" | tr '-' '_')}"
PROJECT_PATH="$PROJECTS_DIR/$PROJECT_NAME"

command -v composer >/dev/null 2>&1 || { echo "ERROR: composer not found on PATH." >&2; exit 1; }
command -v php >/dev/null 2>&1 || { echo "ERROR: php not found on PATH." >&2; exit 1; }
command -v mysql >/dev/null 2>&1 || { echo "ERROR: mysql not found on PATH." >&2; exit 1; }

# Preflight: fail in seconds, before the 15+ minute Composer install.
mysql -u root -e "SELECT 1;" >/dev/null 2>&1 || {
    echo "ERROR: MySQL is not reachable at root@127.0.0.1:3306. Start MySQL/Laragon first." >&2
    exit 1
}
EXISTING_TABLES="$(mysql -u root -N -B -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='$DB_NAME';")"
if [ "${EXISTING_TABLES:-0}" -gt 0 ]; then
    echo "ERROR: database '$DB_NAME' already exists with $EXISTING_TABLES tables." >&2
    echo "Re-run with an explicit database name: bash scaffold.sh $PROJECT_NAME <database-name>" >&2
    exit 1
fi

if [ -e "$PROJECT_PATH" ]; then
    echo "ERROR: $PROJECT_PATH already exists." >&2
    exit 1
fi

echo "==> [1/9] Creating Laravel skeleton (dependencies are NOT installed yet)"
composer create-project laravel/laravel "$PROJECT_PATH" --no-install --no-scripts

echo "==> [2/9] Applying approved templates"
# Laravel 13's skeleton does not ship these directories.
mkdir -p \
    "$PROJECT_PATH/app/Helpers" \
    "$PROJECT_PATH/app/Traits" \
    "$PROJECT_PATH/app/Http/Middleware" \
    "$PROJECT_PATH/app/Http/Controllers" \
    "$PROJECT_PATH/app/Http/Controllers/V1" \
    "$PROJECT_PATH/app/Models/V1" \
    "$PROJECT_PATH/database/factories/V1" \
    "$PROJECT_PATH/tests" \
    "$PROJECT_PATH/lang/en" \
    "$PROJECT_PATH/lang/ar" \
    "$PROJECT_PATH/routes/v1"
cp "$TEMPLATES_DIR/composer.json"                       "$PROJECT_PATH/composer.json"
cp "$TEMPLATES_DIR/bootstrap/app.php"                   "$PROJECT_PATH/bootstrap/app.php"
cp "$TEMPLATES_DIR/bootstrap/providers.php"             "$PROJECT_PATH/bootstrap/providers.php"
cp "$TEMPLATES_DIR/config/app.php"                      "$PROJECT_PATH/config/app.php"
cp "$TEMPLATES_DIR/config/auth.php"                     "$PROJECT_PATH/config/auth.php"
cp "$TEMPLATES_DIR/app/Helpers/Helpers.php"             "$PROJECT_PATH/app/Helpers/Helpers.php"
cp "$TEMPLATES_DIR/app/Traits/ApiResponseTrait.php"     "$PROJECT_PATH/app/Traits/ApiResponseTrait.php"
cp "$TEMPLATES_DIR/app/Http/Middleware/SetLocale.php"   "$PROJECT_PATH/app/Http/Middleware/SetLocale.php"
cp "$TEMPLATES_DIR/app/Http/Controllers/Controller.php" "$PROJECT_PATH/app/Http/Controllers/Controller.php"
cp "$TEMPLATES_DIR/lang/en/messages.php"                "$PROJECT_PATH/lang/en/messages.php"
cp "$TEMPLATES_DIR/lang/ar/messages.php"                "$PROJECT_PATH/lang/ar/messages.php"
cp "$TEMPLATES_DIR/routes/api1.php"                     "$PROJECT_PATH/routes/api1.php"
cp "$TEMPLATES_DIR/routes/documentation.php"            "$PROJECT_PATH/routes/v1/documentation.php"
cp "$TEMPLATES_DIR/app/Http/Controllers/V1/DocumentationController.php" "$PROJECT_PATH/app/Http/Controllers/V1/DocumentationController.php"
cp "$TEMPLATES_DIR/tests/TestCase.php"                  "$PROJECT_PATH/tests/TestCase.php"
cp "$TEMPLATES_DIR/database/factories/V1/UserFactory.php" "$PROJECT_PATH/database/factories/V1/UserFactory.php"
cp "$TEMPLATES_DIR/database/seeders/PassportClientSeeder.php" "$PROJECT_PATH/database/seeders/PassportClientSeeder.php"
cp "$TEMPLATES_DIR/README.md"                           "$PROJECT_PATH/README.md"
cp "$PROJECT_PATH/.env.example" "$PROJECT_PATH/.env"

# Single User model standard: App\Models\V1\User is the only User model
# (see references/models.md and references/architecture.md). Remove the
# Laravel skeleton's default User model and its default UserFactory, and
# install the approved V1 model templates. config/auth.php comes from the
# approved template (api default guard, passport api guard, V1 User import).
rm -f "$PROJECT_PATH/app/Models/User.php"
rm -f "$PROJECT_PATH/database/factories/UserFactory.php"
cp "$TEMPLATES_DIR/app/Models/V1/User.php" "$PROJECT_PATH/app/Models/V1/User.php"
cp "$TEMPLATES_DIR/app/Models/V1/Role.php" "$PROJECT_PATH/app/Models/V1/Role.php"
cp "$TEMPLATES_DIR/app/Models/V1/Permission.php" "$PROJECT_PATH/app/Models/V1/Permission.php"
# Repoint the skeleton's DatabaseSeeder import at the V1 User model, and (once
# Spatie's config is published) its permission config at the V1 Role and
# Permission models. Idempotent; verifies and fails loudly on any mismatch.
# (Done in PHP: the backslash-rich sed equivalents are shell-escape fragile.)
php "$SKILL_DIR/scripts/rewrite-v1-imports.php" "$PROJECT_PATH"
# Fail loudly instead of scaffolding a project whose configuration deviates
# from the approved templates (guards against template or skeleton changes).
grep -q '^use App.Models.V1.User;' "$PROJECT_PATH/config/auth.php" || {
    echo "ERROR: config/auth.php users provider does not reference App\\Models\\V1\\User." >&2
    exit 1
}
grep -qF "env('AUTH_GUARD', 'api')" "$PROJECT_PATH/config/auth.php" || {
    echo "ERROR: config/auth.php default guard is not 'api' (references/authentication.md)." >&2
    exit 1
}
grep -qF "'driver' => 'passport'" "$PROJECT_PATH/config/auth.php" || {
    echo "ERROR: config/auth.php has no 'passport' driver api guard (references/authentication.md)." >&2
    exit 1
}
grep -q '^use App.Models.V1.User;' "$PROJECT_PATH/database/seeders/DatabaseSeeder.php" || {
    echo "ERROR: DatabaseSeeder.php does not reference App\\Models\\V1\\User." >&2
    exit 1
}
grep -rEn 'App.Models.User(;|::)' \
    "$PROJECT_PATH/app" "$PROJECT_PATH/config" "$PROJECT_PATH/database" \
    "$PROJECT_PATH/bootstrap" "$PROJECT_PATH/routes" "$PROJECT_PATH/tests" \
    >/dev/null 2>&1 && {
    echo "ERROR: a reference to the removed default App\\Models\\User remains after scaffold transformation." >&2
    exit 1
}

echo "==> [3/9] Configuring .env for MySQL"
# Match both uncommented (Laravel 13 skeleton ships DB_CONNECTION=sqlite live)
# and commented-out ("# DB_...") defaults; Laravel env loading keeps the FIRST
# occurrence, so values must be edited in place.
sed -i -E \
    -e "s/^APP_NAME=.*/APP_NAME=\"$PROJECT_NAME\"/" \
    -e "s/^#?[[:space:]]*DB_CONNECTION=.*/DB_CONNECTION=mysql/" \
    -e "s/^#?[[:space:]]*DB_HOST=.*/DB_HOST=127.0.0.1/" \
    -e "s/^#?[[:space:]]*DB_PORT=.*/DB_PORT=3306/" \
    -e "s/^#?[[:space:]]*DB_DATABASE=.*/DB_DATABASE=$DB_NAME/" \
    -e "s/^#?[[:space:]]*DB_USERNAME=.*/DB_USERNAME=root/" \
    -e "s/^#?[[:space:]]*DB_PASSWORD=.*/DB_PASSWORD=/" \
    "$PROJECT_PATH/.env"
APP_URL_VALUE="$(grep -E '^APP_URL=' "$PROJECT_PATH/.env" | head -n 1 | cut -d= -f2-)"
printf '\nL5_SWAGGER_CONST_HOST="%s"\nL5_SWAGGER_GENERATE_ALWAYS=true\n' "$APP_URL_VALUE" >> "$PROJECT_PATH/.env"
# Fill the README placeholders that come from the project/database names;
# {{PROJECT_DESCRIPTION}} is filled manually from the stated requirements.
sed -i "s/{{PROJECT_NAME}}/$PROJECT_NAME/g; s/{{DB_NAME}}/$DB_NAME/g" "$PROJECT_PATH/README.md"

echo "==> [4/9] Installing Composer dependencies (the long step)"
echo "    NOTE: vendor/autoload.php does not exist until this step finishes."
echo "    Do NOT run artisan commands (e.g. 'php artisan serve') before this script prints 'Scaffold complete':"
echo "    artisan fails with 'Failed opening required vendor/autoload.php' while Composer is still installing."
(cd "$PROJECT_PATH" && composer install --no-interaction --no-progress)

echo "==> [5/9] Creating MySQL database if it does not exist"
mysql -u root -e "CREATE DATABASE IF NOT EXISTS \`$DB_NAME\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

echo "==> [6/9] Adding the uuid column to the default users migration"
USERS_MIGRATION="$PROJECT_PATH/database/migrations/0001_01_01_000000_create_users_table.php"
sed -i "0,/^\( *\)\\\$table->id();/s//\1\$table->id();\n\1\$table->uuid('uuid')->unique();/" "$USERS_MIGRATION"

echo "==> [7/9] Publishing package migrations and configs"
(cd "$PROJECT_PATH" && php artisan key:generate --no-interaction)
(cd "$PROJECT_PATH" && php artisan vendor:publish --tag=passport-migrations --no-interaction)
(cd "$PROJECT_PATH" && php artisan vendor:publish --tag=telescope-migrations --no-interaction)

# The telescope publish can re-copy Passport's oauth migrations.
# Keep only the earliest migration per oauth table.
(
    cd "$PROJECT_PATH/database/migrations"
    ls | grep '_create_oauth_' | sort | awk -F'_create_oauth_' 'seen[$2]++ {print}' \
        | while IFS= read -r duplicate; do
            rm -f "$duplicate"
            echo "    Removed duplicate oauth migration: $duplicate"
        done
)

(cd "$PROJECT_PATH" && php artisan vendor:publish --provider="Spatie\\Permission\\PermissionServiceProvider" --no-interaction)
# l5-swagger has no usable publish tag for its config; copy it from vendor.
cp "$PROJECT_PATH/vendor/darkaonline/l5-swagger/config/l5-swagger.php" "$PROJECT_PATH/config/l5-swagger.php"

# Skill standard: every table holding application records needs a uuid column.
# Insert it into Spatie's published migration; only the permissions and roles
# blocks use bigIncrements, so one sed patches both.
PERM_MIGRATION="$(ls "$PROJECT_PATH/database/migrations" | grep '_create_permission_tables' | head -n 1 || true)"
test -n "$PERM_MIGRATION" || { echo "ERROR: Spatie permission tables migration not found after vendor:publish." >&2; exit 1; }
sed -i "s/^\( *\)\\\$table->bigIncrements('id');\(.*\)$/\1\$table->bigIncrements('id');\2\n\1\$table->uuid('uuid')->unique();/" \
    "$PROJECT_PATH/database/migrations/$PERM_MIGRATION"

# Point Spatie's published config at the V1 Role and Permission models
# (references/architecture.md). The models themselves were installed from
# templates in step [2/9], so the classes exist before anything boots.
php "$SKILL_DIR/scripts/rewrite-v1-imports.php" "$PROJECT_PATH"
grep -q '^use App.Models.V1.Role;' "$PROJECT_PATH/config/permission.php" \
    && grep -q '^use App.Models.V1.Permission;' "$PROJECT_PATH/config/permission.php" || {
    echo "ERROR: config/permission.php does not reference the App\\Models\\V1\\Role and App\\Models\\V1\\Permission models." >&2
    exit 1
}
grep -qF "'role' => Role::class," "$PROJECT_PATH/config/permission.php" \
    && grep -qF "'permission' => Permission::class," "$PROJECT_PATH/config/permission.php" || {
    echo "ERROR: config/permission.php models.role / models.permission do not resolve to the imported V1 models." >&2
    exit 1
}

echo "==> [8/9] Running migrations and preparing Passport"
(cd "$PROJECT_PATH" && php artisan migrate --no-interaction --force)
(cd "$PROJECT_PATH" && php artisan passport:keys --no-interaction)
# No `passport:client --personal` here: the PassportClientSeeder template
# (registered in DatabaseSeeder by the module seeder step) is the single
# authoritative creator of the personal access client, in the main database
# and in the test database alike.

echo "==> [9/9] Seeding (only once the Roles and Permissions Seeder is registered)"
if grep -q "RolesAndPermissionsSeeder" "$PROJECT_PATH/database/seeders/DatabaseSeeder.php"; then
    (cd "$PROJECT_PATH" && php artisan db:seed --no-interaction --force)
else
    echo "    Skipped: RolesAndPermissionsSeeder is not registered in DatabaseSeeder yet."
    echo "    Run 'php artisan db:seed' after implementing the module seeders."
fi

echo "==> Scaffold complete: $PROJECT_PATH (database: $DB_NAME)"
echo "    Completion gate: this project is BLOCKED until"
echo "    'bash scripts/completion-gate.sh $PROJECT_NAME' passes (SKILL.md, Project Completion Gate)."
echo "    A BLOCKED project must not be handed off to the Frontend Team or any other team."
