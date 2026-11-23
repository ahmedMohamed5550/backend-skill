#!/usr/bin/env bash
set -uo pipefail

# Backend Project Completion Gate
#
# SKILL.md section "Project Completion Gate": a project is READY only when every
# gate criterion passes; otherwise it stays BLOCKED and must not be handed off
# to the Frontend Team or any other team. This script is the machine-checkable
# half of the gate; the judgment half (requirement coverage, documentation
# accuracy, authentication/integration correctness) is described in SKILL.md
# and must still be verified before reporting READY.
#
# Usage:
#   bash completion-gate.sh <project-folder-name>
#
#   <project-folder-name>  lowercase kebab-case project folder name inside the
#                          configured Projects Workspace (scripts/config.sh).
#
# Verdicts:
#   READY   every check passes (exit code 0).
#   BLOCKED at least one check failed (exit code 1). The failure list is
#           printed; fix the root causes and re-run the gate.
#   A missing project folder or unusable environment is an error (exit code 2)
#           and also means the project is not READY.
#
# Checks and the gate criteria they verify (SKILL.md, Gate Criteria):
#   CHECK 01 dependencies -> criterion 2 (Composer and dependencies installed)
#   CHECK 02 configuration -> criterion 3 (.env, MySQL connection, APP_KEY)
#   CHECK 03 database     -> criterion 3 (migrations fully executed)
#   CHECK 04 routes       -> criterion 4 (routes compile, api/v1 registered)
#   CHECK 05 swagger      -> criterion 5 (OpenAPI specification generates)
#   CHECK 06 docs files   -> criterion 5 (required documentation files exist)
#   CHECK 07 standards    -> criterion 1 (machine-checkable skill requirements:
#                            single V1 User model/factory, README without
#                            unresolved placeholders)
#   CHECK 08 tests        -> criterion 6 (complete required test suite passes)
#   CHECK 09 boot         -> criterion 7 (application boots and serves
#                            GET /api/v1/documentation successfully)
#
# Notes:
#   - Run this script in the background: the full test suite (CHECK 08) can
#     take several minutes.
#   - Tests run through phpunit.xml, so they target the isolated *_test
#     database (references/testing.md section 4), never the main database.
#   - Override the smoke-test port with GATE_PORT=<port> when 8123 is busy.

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=config.sh
source "$SCRIPT_DIR/config.sh"

PROJECT_NAME="${1:?Usage: completion-gate.sh <project-folder-name>}"
PROJECT_PATH="$PROJECTS_DIR/$PROJECT_NAME"
GATE_PORT="${GATE_PORT:-8123}"
SMOKE_URL="http://127.0.0.1:${GATE_PORT}/api/v1/documentation"

if [ ! -d "$PROJECT_PATH" ]; then
    echo "ERROR: project folder not found: $PROJECT_PATH" >&2
    echo "The gate cannot run without the project; the project is not READY." >&2
    exit 2
fi
if ! command -v php >/dev/null 2>&1; then
    echo "ERROR: php not found on PATH." >&2
    exit 2
fi

echo "============================================================"
echo " Backend Project Completion Gate: $PROJECT_NAME"
echo "============================================================"

FAILURES=()

pass() {
    echo "  PASS  $1"
}

fail() {
    echo "  FAIL  $1"
    FAILURES+=("$2")
}

art() {
    # Run an artisan command from the project root with output visible.
    (cd "$PROJECT_PATH" && php artisan "$@")
}

echo "---- CHECK 01 dependencies: Composer and installed dependencies"
if ! command -v composer >/dev/null 2>&1; then
    fail "CHECK 01" "Composer is not available on PATH."
elif [ ! -f "$PROJECT_PATH/vendor/autoload.php" ]; then
    fail "CHECK 01" "vendor/autoload.php is missing; run 'composer install' in the project."
else
    if (cd "$PROJECT_PATH" && composer validate --no-check-all --no-interaction); then
        pass "composer.json is valid and vendor/autoload.php exists"
    else
        fail "CHECK 01" "'composer validate' failed; fix the Composer configuration or installation."
    fi
fi

echo "---- CHECK 02 configuration: .env, MySQL connection, APP_KEY"
if [ ! -f "$PROJECT_PATH/.env" ]; then
    fail "CHECK 02" ".env is missing in the project root."
else
    CONFIG_OK=1
    grep -Eq '^DB_CONNECTION=mysql' "$PROJECT_PATH/.env" || { fail "CHECK 02" ".env does not set DB_CONNECTION=mysql."; CONFIG_OK=0; }
    grep -Eq '^DB_DATABASE=[^[:space:]]+' "$PROJECT_PATH/.env" || { fail "CHECK 02" ".env does not set a non-empty DB_DATABASE."; CONFIG_OK=0; }
    grep -Eq '^APP_KEY=.+' "$PROJECT_PATH/.env" || { fail "CHECK 02" ".env does not set APP_KEY (run 'php artisan key:generate')."; CONFIG_OK=0; }
    [ "$CONFIG_OK" -eq 1 ] && pass ".env configures MySQL connection, database name, and APP_KEY"
fi

echo "---- CHECK 03 database: all migrations executed"
MIGRATE_STATUS_OUTPUT="$(art migrate:status --no-interaction 2>&1)"
MIGRATE_RC=$?
if [ "$MIGRATE_RC" -ne 0 ]; then
    fail "CHECK 03" "'php artisan migrate:status' failed (rc=$MIGRATE_RC): database unreachable or not configured."
elif printf '%s' "$MIGRATE_STATUS_OUTPUT" | grep -q 'Pending'; then
    fail "CHECK 03" "migrate:status reports Pending migrations; run 'php artisan migrate'."
else
    pass "database reachable and every migration has been executed"
fi
# Seeder evidence: the required test suite (CHECK 08) re-runs DatabaseSeeder
# with public $seed = true on every refresh (references/testing.md 7.1), so a
# passing CHECK 08 also proves migrations, seeders, and required
# configuration work end to end. Broken seeders surface here as CHECK 03 or
# CHECK 08 failures.

echo "---- CHECK 04 routes: route files compile and api/v1 is registered"
ROUTE_LIST_OUTPUT="$(art route:list 2>&1)"
ROUTE_RC=$?
if [ "$ROUTE_RC" -ne 0 ]; then
    fail "CHECK 04" "'php artisan route:list' failed (rc=$ROUTE_RC); a route file does not compile."
elif ! art route:list --path=api/v1 | grep -q 'api/v1'; then
    fail "CHECK 04" "no routes registered under the api/v1 prefix."
else
    pass "routes compile and the api/v1 prefix is registered"
fi

echo "---- CHECK 05 swagger: OpenAPI specification generates"
if art l5-swagger:generate --no-interaction; then
    pass "L5-Swagger generated the OpenAPI specification from the annotations"
else
    fail "CHECK 05" "'php artisan l5-swagger:generate' failed; Swagger annotations are invalid or incomplete."
fi

echo "---- CHECK 06 documentation: required docs files exist"
DOCS_OK=1
for DOC_FILE in \
    "$PROJECT_PATH/docs/API_DOCUMENTATION.md" \
    "$PROJECT_PATH/docs/DATABASE_DOCUMENTATION.md" \
    "$PROJECT_PATH/docs/IMPLEMENTATION_PLAN.md" \
    "$PROJECT_PATH/README.md"
do
    if [ ! -s "$DOC_FILE" ]; then
        fail "CHECK 06" "required documentation file is missing or empty: ${DOC_FILE#$PROJECT_PATH/}"
        DOCS_OK=0
    fi
done
[ "$DOCS_OK" -eq 1 ] && pass "API_DOCUMENTATION.md, DATABASE_DOCUMENTATION.md, IMPLEMENTATION_PLAN.md, README.md all exist"

echo "---- CHECK 07 standards: approved structure machine checks"
STANDARDS_OK=1
if [ ! -f "$PROJECT_PATH/app/Models/V1/User.php" ]; then
    fail "CHECK 07" "app/Models/V1/User.php is missing; the single V1 User model standard is violated."
    STANDARDS_OK=0
fi
if [ -f "$PROJECT_PATH/app/Models/User.php" ]; then
    fail "CHECK 07" "app/Models/User.php exists; the default User model must never be recreated."
    STANDARDS_OK=0
fi
if [ ! -f "$PROJECT_PATH/database/factories/V1/UserFactory.php" ]; then
    fail "CHECK 07" "database/factories/V1/UserFactory.php is missing."
    STANDARDS_OK=0
fi
if [ -f "$PROJECT_PATH/database/factories/UserFactory.php" ]; then
    fail "CHECK 07" "database/factories/UserFactory.php exists; the default UserFactory must never be recreated."
    STANDARDS_OK=0
fi
if grep -q '{{' "$PROJECT_PATH/README.md" 2>/dev/null; then
    fail "CHECK 07" "README.md still contains unresolved {{...}} placeholders."
    STANDARDS_OK=0
fi
[ "$STANDARDS_OK" -eq 1 ] && pass "single V1 User model/factory in place, README has no unresolved placeholders"

echo "---- CHECK 08 tests: complete required test suite"
# Sequential run (references/testing.md 14): tests share one MySQL test
# database; never use --parallel. Note: no --no-interaction here —
# `artisan test` forwards unknown options to PHPUnit, and PHPUnit 12
# rejects --no-interaction.
if art test; then
    pass "the complete test suite passed"
else
    fail "CHECK 08" "'php artisan test' failed; the test suite does not fully pass (criterion 6)."
fi

echo "---- CHECK 09 boot: application boots and serves the documentation endpoint"
SERVE_PID=""
cleanup_server() {
    if [ -n "$SERVE_PID" ]; then
        # Tree-kill first: 'php artisan serve' spawns a 'php -S' child that a
        # plain kill would orphan on Windows, keeping the port busy.
        if command -v taskkill >/dev/null 2>&1; then
            taskkill //F //T //PID "$SERVE_PID" >/dev/null 2>&1 || true
        fi
        kill "$SERVE_PID" 2>/dev/null
        wait "$SERVE_PID" 2>/dev/null
        SERVE_PID=""
    fi
}
trap cleanup_server EXIT

(cd "$PROJECT_PATH" && php artisan serve --host=127.0.0.1 --port="$GATE_PORT") &
SERVE_PID=$!

SMOKE_CODE="000"
for _ in $(seq 1 30); do
    SMOKE_CODE="$(curl -s -o /dev/null -w '%{http_code}' "$SMOKE_URL" 2>/dev/null)"
    [ "$SMOKE_CODE" != "000" ] && break
    sleep 1
done

if [ "$SMOKE_CODE" = "200" ]; then
    pass "application boots; GET /api/v1/documentation returned 200"
elif [ "$SMOKE_CODE" = "000" ]; then
    fail "CHECK 09" "the application did not answer on port $GATE_PORT within 30s (set GATE_PORT=<free port> if the port is busy)."
else
    fail "CHECK 09" "GET /api/v1/documentation answered HTTP $SMOKE_CODE instead of 200; the app boots but the documentation endpoint does not serve docs/API_DOCUMENTATION.md correctly."
fi
cleanup_server

echo "============================================================"
if [ "${#FAILURES[@]}" -eq 0 ]; then
    echo " RESULT: READY"
    echo " All machine checks passed. Confirm the judgment checks from"
    echo " SKILL.md (requirement coverage, documentation accuracy,"
    echo " authentication and integration correctness), then the project"
    echo " may be reported READY and handed off."
    echo "============================================================"
    exit 0
else
    echo " RESULT: BLOCKED (${#FAILURES[@]} failure(s))"
    for FAILURE in "${FAILURES[@]}"; do
        echo "   - $FAILURE"
    done
    echo " The project is NOT ready. Fix the failures above, re-run:"
    echo "   bash completion-gate.sh $PROJECT_NAME"
    echo " A BLOCKED project must not be handed off to the Frontend Team"
    echo " or any other team."
    echo "============================================================"
    exit 1
fi
