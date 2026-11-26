<?php

/**
 * Backend Skill — Local Developer Database Configuration Reader
 *
 * Reads and validates the skill workspace's local.config.json (template:
 * local.config.example.json) — the single source of a developer's personal
 * MySQL connection settings (SKILL.md, Default Database Configuration).
 *
 * Usage:
 *   php read-local-config.php /path/to/local.config.json
 *
 * On success prints five lines on STDOUT, in order, with no quoting or
 * escaping (values are validated to be single-line):
 *   host
 *   port
 *   username
 *   password
 *   LOCAL_CONFIG_END
 *
 * The sentinel line is required because an empty password would otherwise end
 * the output with blank lines, which the consuming bash command substitution
 * strips — the sentinel keeps every field, including an empty one, intact.
 *
 * On a missing or invalid file it prints a complete, actionable list of every
 * problem to STDERR and exits 1. It never guesses credentials and never
 * returns defaults: an absent value is a hard error.
 *
 * Scripts consume this through scripts/local-config.sh, which parses the four
 * lines into LOCAL_DB_HOST, LOCAL_DB_PORT, LOCAL_DB_USERNAME, and
 * LOCAL_DB_PASSWORD without eval (a value is never executed as shell code).
 */

$exitUsage = static function (string $message): never {
    fwrite(STDERR, "ERROR: {$message}\n");
    exit(1);
};

$configFile = $argv[1] ?? null;
if ($configFile === null || $configFile === '') {
    $exitUsage('usage: php read-local-config.php <path-to-local.config.json>');
}
if (!is_file($configFile) || !is_readable($configFile)) {
    $exitUsage(
        "the local database configuration file is missing or unreadable: {$configFile}\n"
        . "Create it from the template local.config.example.json at the skill root"
        . " and fill in your local MySQL settings."
    );
}

$raw = file_get_contents($configFile);
if ($raw === false) {
    $exitUsage("could not read the local database configuration file: {$configFile}");
}

$data = json_decode($raw, true);
if (!is_array($data)) {
    $exitUsage(
        "local.config.json is not valid JSON (json error: " . json_last_error_msg() . "):\n{$configFile}\n"
        . "Fix the JSON syntax, starting from the local.config.example.json template."
    );
}

$errors = [];

if (array_keys($data) !== ['db']) {
    $errors[] = 'the configuration must be a single JSON object with exactly one top-level key: "db".';
}

$db = $data['db'] ?? null;
if (!is_array($db)) {
    $errors[] = 'the "db" key must be an object holding host, port, username, and password.';
    $db = [];
} elseif (($diff = array_diff(array_keys($db), ['host', 'port', 'username', 'password'])) !== []) {
    $errors[] = sprintf(
        'unknown "db" key(s): %s. Allowed keys are exactly: host, port, username, password.',
        implode(', ', array_map(static fn ($key): string => '"'.$key.'"', array_values($diff)))
    );
}

/**
 * Shared string checks. A single quote is rejected because .env values are
 * written single-quoted (the only Laravel dotenv form with no expansion or
 * escape processing), and a literal single quote cannot live inside a
 * single-quoted dotenv value. Control characters would corrupt both .env and
 * the MySQL client invocation.
 */
$checkString = static function (string $field, mixed $value, bool $allowEmpty): ?string {
    if (!is_string($value)) {
        return "db.{$field} must be a string" . ($allowEmpty ? ' (an empty string is allowed)' : '') . '.';

    }
    if (preg_match('/[\x00-\x1f\x7f]/', $value) === 1) {
        return "db.{$field} must not contain control characters or line breaks.";
    }
    if (str_contains($value, "'")) {
        return "db.{$field} must not contain a single quote ('): values are stored single-quoted in a Laravel .env file, which cannot represent a literal single quote. Choose a {$field} without one.";
    }
    if (!$allowEmpty && trim($value) === '') {
        return "db.{$field} must be a non-empty string.";
    }
    if (preg_match('/\s/', $value) === 1) {
        return "db.{$field} must not contain whitespace.";
    }

    return null;
};

$host = $db['host'] ?? null;
if ($host === null && !array_key_exists('host', $db)) {
    $errors[] = 'db.host is missing; set it to your local MySQL host (for example 127.0.0.1).';
} else {
    $error = $checkString('host', $host, allowEmpty: false);
    if ($error !== null) {
        $errors[] = $error;
    }
}

$port = $db['port'] ?? null;
if ($port === null && !array_key_exists('port', $db)) {
    $errors[] = 'db.port is missing; set it to your local MySQL port (a plain integer, for example 3306).';
} elseif (!is_int($port)) {
    $errors[] = 'db.port must be a JSON integer (a plain unquoted number, for example 3306' . (
        is_string($port) ? '; found the quoted string "'.$port.'"' : ''
    ) . ').';
} elseif ($port < 1 || $port > 65535) {
    $errors[] = "db.port must be between 1 and 65535 (found: {$port}).";
}

$username = $db['username'] ?? null;
if ($username === null && !array_key_exists('username', $db)) {
    $errors[] = 'db.username is missing; set it to your local MySQL user.';
} else {
    $error = $checkString('username', $username, allowEmpty: false);
    if ($error !== null) {
        $errors[] = $error;
    }
}

if (!array_key_exists('password', $db)) {
    // An empty password is valid; an absent password key is not: the loader
    // must never have to decide between "missing" and "empty".
    $errors[] = 'db.password is missing; set it to your local MySQL password as a string (use "" when the password is empty).';
} else {
    $error = $checkString('password', $db['password'], allowEmpty: true);
    if ($error !== null) {
        $errors[] = $error;
    }
}

if ($errors !== []) {
    fwrite(STDERR, "ERROR: the Backend Skill local database configuration is invalid:\n{$configFile}\n\n");
    foreach ($errors as $error) {
        fwrite(STDERR, "  - {$error}\n");
    }
    fwrite(STDERR, "\nRequired structure (start from the local.config.example.json template at the skill root):\n");
    fwrite(STDERR, <<<'JSON'
{
    "db": {
        "host": "<your MySQL host>",
        "port": <your MySQL port as an integer>,
        "username": "<your MySQL user>",
        "password": "<your MySQL password, or "" when empty>"
    }
}

JSON);
    fwrite(STDERR, "\nDatabase credentials are never guessed or defaulted; fix local.config.json and re-run.\n");
    exit(1);
}

// Success: four value lines in fixed order (values guaranteed single-line),
// then the sentinel line the bash loader requires.
echo $host."\n";
echo $port."\n";
echo $username."\n";
echo $db['password']."\n";
echo "LOCAL_CONFIG_END\n";
