<?php

/**
 * Backend Skill — Generated Project .env Database Writer
 *
 * Writes the database block of a freshly scaffolded project's .env from the
 * values loaded through scripts/local-config.sh (local.config.json at the
 * skill root). It replaces the former in-script sed pipeline, whose
 * replacement strings could not safely carry passwords containing sed
 * metacharacters.
 *
 * Usage (values arrive through the environment, never argv — a password is
 * data and must not appear in a command line):
 *   DB_CONNECTION=mysql DB_DATABASE=... DB_HOST=... DB_PORT=... \
 *   DB_USERNAME=... DB_PASSWORD=... php write-env-db.php <project-path>
 *
 * Behavior:
 * - Laravel env loading keeps the FIRST occurrence of a key, so each key is
 *   replaced in place at its first occurrence — commented-out skeleton
 *   defaults ("# DB_HOST=...") included — and never duplicated.
 * - Values are written unquoted when they consist only of [A-Za-z0-9._-];
 *   otherwise they are written single-quoted, the only Laravel dotenv form
 *   with no variable expansion and no escape processing. read-local-config.php
 *   and the project-name rules already exclude single quotes and control
 *   characters; this script fails hard if one still arrives (defense in
 *   depth) instead of writing a line the framework would misparse.
 * - After writing, every line is verified against the exact expected form.
 */

$exitError = static function (string $message): never {
    fwrite(STDERR, "ERROR: {$message}\n");
    exit(1);
};

$projectPath = $argv[1] ?? null;
if ($projectPath === null || $projectPath === '') {
    $exitError('usage: php write-env-db.php <project-path> (database values arrive through environment variables)');
}
$envFile = $projectPath.'/.env';
if (!is_file($envFile) || !is_writable($envFile)) {
    $exitError("the project .env file is missing or not writable: {$envFile}");
}

$keys = ['DB_CONNECTION', 'DB_DATABASE', 'DB_HOST', 'DB_PORT', 'DB_USERNAME'];
$values = [];
$missing = [];
foreach ($keys as $key) {
    $value = getenv($key);
    if ($value === false || $value === '') {
        $missing[] = $key;

        continue;
    }
    $values[$key] = $value;
}
// DB_PASSWORD is read separately from the other keys: an empty string is a
// valid password, so only a variable that is absent from the environment at
// all is a hard error.
$password = getenv('DB_PASSWORD');
if ($password === false) {
    $missing[] = 'DB_PASSWORD';
}
if ($missing !== []) {
    $exitError(
        'missing database environment variables: '.implode(', ', $missing)."\n"
        .'Load the developer settings with scripts/local-config.sh (local.config.json)'
        .' and pass every value through the environment.'
    );
}
$values['DB_PASSWORD'] = $password;

$envQuote = static function (string $value): string {
    if ($value === '') {
        return '';
    }
    if (preg_match('/^[A-Za-z0-9._-]+$/', $value) === 1) {
        return $value;
    }
    if (preg_match("/['\x00-\x1f\x7f]/", $value) === 1) {
        $exitError(
            'a database value cannot be represented in a Laravel .env file'
            ." because it contains a single quote or a control character; check"
            .' local.config.json and the project name.'
        );
    }

    return "'".$value."'";
};

$env = file_get_contents($envFile);
if ($env === false) {
    $exitError("could not read the project .env file: {$envFile}");
}

$appended = [];
foreach ($values as $key => $value) {
    $line = $key.'='.$envQuote($value);
    $updated = preg_replace_callback(
        '/^#?[ \t]*'.preg_quote($key, '/').'=[^\r\n]*(\r?)$/m',
        static function (array $matches) use ($key, $value, $envQuote): string {
            // (\r?) preserves the file's original line ending on this line.
            return $key.'='.$envQuote($value).$matches[1];
        },
        $env,
        1,
        $count,
    );
    if ($updated === null) {
        $exitError("a regular expression failure occurred while writing {$key} to {$envFile}.");
    }
    if ($count === 1) {
        $env = $updated;

        continue;
    }
    // The skeleton .env always carries the DB_ keys, so appending is a safety
    // net for unexpected skeletons, not a normal path.
    $appended[] = $line;
    $env = $env.(str_ends_with($env, "\n") ? '' : "\n").$line."\n";
}

if (file_put_contents($envFile, $env) === false) {
    $exitError("could not write the project .env file: {$envFile}");
}

// Post-write verification: Laravel reads the FIRST ACTIVE occurrence of a
// key, so that exact line must equal the intended value. An unexpected number
// of additional active lines cannot change what the framework reads, but it
// signals an unusual skeleton — reported as a warning instead of silently
// ignored.
foreach ($values as $key => $value) {
    $expected = $key.'='.$envQuote($value);
    $active = array_values(preg_grep('/^'.preg_quote($key, '/').'=/', explode("\n", $env)) ?: []);
    if ($active === [] || trim((string) $active[0]) !== $expected) {
        $exitError("post-write verification failed for {$key} in {$envFile}; the .env may now be inconsistent.");
    }
    if (count($active) > 1) {
        fwrite(STDERR, "WARNING: {$envFile} contains ".count($active)." active {$key} lines; Laravel reads the first (verified above). Remove the stale duplicates.\n");
    }
}

foreach ($values as $key => $value) {
    $written = $key.'='.$envQuote($value);
    echo in_array($written, $appended, true)
        ? "    {$key} appended to .env\n"
        : "    {$key} written to .env\n";
}
