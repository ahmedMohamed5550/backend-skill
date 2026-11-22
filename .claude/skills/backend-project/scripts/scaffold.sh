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
#     existing oauth tables); passport:keys + passport:client --personal instead.
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
    "$PROJECT_PATH/lang/en" \
    "$PROJECT_PATH/lang/ar"
cp "$TEMPLATES_DIR/composer.json"                       "$PROJECT_PATH/composer.json"
cp "$TEMPLATES_DIR/bootstrap/app.php"                   "$PROJECT_PATH/bootstrap/app.php"
cp "$TEMPLATES_DIR/bootstrap/providers.php"             "$PROJECT_PATH/bootstrap/providers.php"
cp "$TEMPLATES_DIR/config/app.php"                      "$PROJECT_PATH/config/app.php"
cp "$TEMPLATES_DIR/app/Helpers/Helpers.php"             "$PROJECT_PATH/app/Helpers/Helpers.php"
cp "$TEMPLATES_DIR/app/Traits/ApiResponseTrait.php"     "$PROJECT_PATH/app/Traits/ApiResponseTrait.php"
cp "$TEMPLATES_DIR/app/Http/Middleware/SetLocale.php"   "$PROJECT_PATH/app/Http/Middleware/SetLocale.php"
cp "$TEMPLATES_DIR/app/Http/Controllers/Controller.php" "$PROJECT_PATH/app/Http/Controllers/Controller.php"
cp "$TEMPLATES_DIR/lang/en/messages.php"                "$PROJECT_PATH/lang/en/messages.php"
cp "$TEMPLATES_DIR/lang/ar/messages.php"                "$PROJECT_PATH/lang/ar/messages.php"
cp "$TEMPLATES_DIR/routes/api1.php"                     "$PROJECT_PATH/routes/api1.php"
cp "$TEMPLATES_DIR/routes/documentation.php"            "$PROJECT_PATH/routes/documentation.php"
cp "$TEMPLATES_DIR/app/Http/Controllers/V1/DocumentationController.php" "$PROJECT_PATH/app/Http/Controllers/V1/DocumentationController.php"
cp "$TEMPLATES_DIR/README.md"                           "$PROJECT_PATH/README.md"
cp "$PROJECT_PATH/.env.example" "$PROJECT_PATH/.env"

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

echo "==> [8/9] Running migrations and preparing Passport"
(cd "$PROJECT_PATH" && php artisan migrate --no-interaction --force)
(cd "$PROJECT_PATH" && php artisan passport:keys --no-interaction)
(cd "$PROJECT_PATH" && php artisan passport:client --personal --name="Personal Access Client" --no-interaction)

echo "==> [9/9] Seeding (only once the Roles and Permissions Seeder is registered)"
if grep -q "RolesAndPermissionsSeeder" "$PROJECT_PATH/database/seeders/DatabaseSeeder.php"; then
    (cd "$PROJECT_PATH" && php artisan db:seed --no-interaction --force)
else
    echo "    Skipped: RolesAndPermissionsSeeder is not registered in DatabaseSeeder yet."
    echo "    Run 'php artisan db:seed' after implementing the module seeders."
fi

echo "==> Scaffold complete: $PROJECT_PATH (database: $DB_NAME)"
