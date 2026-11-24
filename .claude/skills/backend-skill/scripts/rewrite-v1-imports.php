<?php

/**
 * Backend Skill scaffold helper: rewrites V1 model imports and package
 * configuration references in a freshly scaffolded project.
 *
 * Called by scripts/scaffold.sh (twice: once after template application,
 * once after the Spatie vendor:publish step). Idempotent: replacing
 * already-replaced strings is a no-op, so calling it again is safe.
 *
 * Why PHP instead of sed: the backslash-rich patterns sed would need
 * (App\Models\V1\...) are shell-escape fragile across environments; PHP
 * string literals here have no shell layer to pass through.
 *
 * Usage:
 *   php rewrite-v1-imports.php <project-path>
 *
 * Exit codes: 0 all rewrites verified, 1 a rewrite failed verification.
 */

if ($argc < 2) {
    fwrite(STDERR, "ERROR: usage: php rewrite-v1-imports.php <project-path>\n");
    exit(1);
}

$projectPath = rtrim($argv[1], '/\\');
$failures = [];

/**
 * Apply a list of exact string replacements to a file, then verify that a
 * required marker exists and a forbidden marker does not.
 */
function rewriteFile(string $file, array $replacements, array $mustContain, array $mustNotContain): string
{
    if (! file_exists($file)) {
        return "missing file: {$file}";
    }

    $content = file_get_contents($file);
    foreach ($replacements as $from => $to) {
        if ($from !== $to && str_contains($content, $from)) {
            $content = str_replace($from, $to, $content);
        }
    }
    file_put_contents($file, $content);

    foreach ($mustContain as $marker) {
        if (! str_contains($content, $marker)) {
            return "{$file} does not contain: {$marker}";
        }
    }
    foreach ($mustNotContain as $marker) {
        if (str_contains($content, $marker)) {
            return "{$file} still contains: {$marker}";
        }
    }

    return '';
}

// --- 1. DatabaseSeeder: repoint the skeleton User import at the V1 model ---
$databaseSeeder = $projectPath.'/database/seeders/DatabaseSeeder.php';
$result = rewriteFile(
    $databaseSeeder,
    ['use App\Models\User;' => 'use App\Models\V1\User;'],
    ['use App\Models\V1\User;'],
    ['use App\Models\User;']
);
if ($result !== '') {
    $failures[] = $result;
}

// --- 2. config/permission.php: point Spatie at the App\Models\V1 models ---
// The file exists only after the Spatie vendor:publish step; skip silently
// before that (the scaffold calls this helper again after publishing).
$permissionConfig = $projectPath.'/config/permission.php';
if (file_exists($permissionConfig)) {
    // The published Spatie 6 import block, replaced by the approved block.
    $spatieImportBlock =
        "use Spatie\\Permission\\DefaultTeamResolver;\n".
        "use Spatie\\Permission\\Models\\Permission;\n".
        "use Spatie\\Permission\\Models\\Role;";
    $approvedImportBlock =
        "use App\\Models\\V1\\Permission;\n".
        "use App\\Models\\V1\\Role;\n".
        "use Spatie\\Permission\\DefaultTeamResolver;";

    // Per-line fallbacks for other Spatie 6.x import orderings, plus the
    // inline FQCN forms should a future version stop shipping imports.
    $replacements = [
        $spatieImportBlock => $approvedImportBlock,
        'use Spatie\\Permission\\Models\\Permission;' => 'use App\\Models\\V1\\Permission;',
        'use Spatie\\Permission\\Models\\Role;' => 'use App\\Models\\V1\\Role;',
        "'permission' => \\Spatie\\Permission\\Models\\Permission::class," => "'permission' => App\\Models\\V1\\Permission::class,",
        "'role' => \\Spatie\\Permission\\Models\\Role::class," => "'role' => App\\Models\\V1\\Role::class,",
    ];

    $result = rewriteFile(
        $permissionConfig,
        $replacements,
        [
            'use App\\Models\\V1\\Permission;',
            'use App\\Models\\V1\\Role;',
            "'permission' => Permission::class,",
            "'role' => Role::class,",
        ],
        [
            'use Spatie\\Permission\\Models\\Permission;',
            'use Spatie\\Permission\\Models\\Role;',
            "'permission' => \\Spatie\\Permission\\Models\\Permission::class,",
            "'role' => \\Spatie\\Permission\\Models\\Role::class,",
        ]
    );
    if ($result !== '') {
        $failures[] = $result;
    }
}

if ($failures !== []) {
    fwrite(STDERR, "ERROR: rewrite-v1-imports.php failed:\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, "  - {$failure}\n");
    }
    exit(1);
}

exit(0);
