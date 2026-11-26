#!/usr/bin/env bash

# Backend Skill — Local Developer Database Configuration Loader
#
# Every developer keeps their personal MySQL connection settings in
# local.config.json at the skill root (template: local.config.example.json).
# This is the single credential source for every skill script that talks to
# MySQL or configures a generated project (SKILL.md, Default Database
# Configuration). Credentials are never hardcoded and never defaulted: a
# missing or invalid local.config.json is a hard, clearly reported error.
#
# Usage (from a script that already sourced config.sh):
#   source "$SCRIPT_DIR/local-config.sh"
#   load_local_db_config
#
# On success exports:
#   LOCAL_DB_HOST       validated non-empty string, no whitespace
#   LOCAL_DB_PORT       validated integer 1-65535
#   LOCAL_DB_USERNAME   validated non-empty string, no whitespace
#   LOCAL_DB_PASSWORD   validated string, may be empty
#
# The values come from read-local-config.php as four lines plus a trailing
# sentinel line and are parsed with mapfile, never eval: a password is data,
# never shell code.

# shellcheck shell=bash

load_local_db_config() {
    : "${SKILL_DIR:?SKILL_DIR is not set; source config.sh before local-config.sh}"
    local config_file="$SKILL_DIR/local.config.json"

    if [ ! -f "$config_file" ]; then
        {
            echo "ERROR: the Backend Skill local database configuration is missing:"
            echo "    $config_file"
            echo ""
            echo "Every developer provides personal MySQL settings in local.config.json at"
            echo "the skill root. Create it from the template and fill in your local values:"
            echo ""
            echo "    cp \"$SKILL_DIR/local.config.example.json\" \"$config_file\""
            echo ""
            echo "Then edit the copy and set db.host, db.port, db.username, and db.password"
            echo "(the template documents the exact structure). The file is git-ignored and"
            echo "must never be committed or copied into a generated project."
            echo ""
            echo "Database credentials are never guessed or defaulted; re-run after creating the file."
        } >&2
        exit 1
    fi

    local parsed
    if ! parsed="$(php "$SKILL_DIR/scripts/read-local-config.php" "$config_file")"; then
        # read-local-config.php already printed every specific problem and the
        # required structure; nothing to add here.
        exit 1
    fi

    local fields=()
    mapfile -t fields <<<"$parsed"
    # read-local-config.php prints the four values and then a sentinel line:
    # without it, an empty password would end the output in blank lines that
    # the command substitution above strips, silently losing the field.
    if [ "${#fields[@]}" -ne 5 ] || [ "${fields[4]}" != "LOCAL_CONFIG_END" ]; then
        echo "ERROR: read-local-config.php did not return the expected output format; the local configuration loader is broken (scripts/local-config.sh, scripts/read-local-config.php)." >&2
        exit 1
    fi

    LOCAL_DB_HOST="${fields[0]}"
    LOCAL_DB_PORT="${fields[1]}"
    LOCAL_DB_USERNAME="${fields[2]}"
    LOCAL_DB_PASSWORD="${fields[3]}"
    export LOCAL_DB_HOST LOCAL_DB_PORT LOCAL_DB_USERNAME LOCAL_DB_PASSWORD
}
