<?php

/**
 * Dependency license gate.
 *
 * Reads `composer licenses --no-dev --format=json` and fails when a production
 * dependency is outside the allowlist in docs/OSS-POLICY.md.
 *
 * Usage: php scripts/check-licenses.php
 */
$allowlist = [
    'MIT',
    'ISC',
    'BSD-2-Clause',
    'BSD-3-Clause',
    'Apache-2.0',
    'MPL-2.0',
    'LGPL-2.1',
    'LGPL-2.1-or-later',
    'LGPL-2.2',
    'LGPL-3.0',
    'LGPL-3.0-or-later',
    'AGPL-3.0-only',
    'AGPL-3.0-or-later',
    'GPL-2.0-only',
    'GPL-2.0-or-later',
    'GPL-3.0-only',
    'GPL-3.0-or-later',
    '0BSD',
    'CC0-1.0',
    'Unlicense',
];

$nullDevice = PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null';
$command = 'composer licenses --no-dev --format=json 2>'.$nullDevice;
$output = shell_exec($command);

if (! is_string($output) || trim($output) === '') {
    fwrite(STDERR, "Could not read composer licenses output.\n");
    exit(1);
}

$decoded = json_decode($output, true);

if (! is_array($decoded)) {
    fwrite(STDERR, "Composer license output was not valid JSON.\n");
    exit(1);
}

$dependencies = $decoded['dependencies'] ?? $decoded;
$violations = [];
$unknown = [];

foreach ($dependencies as $name => $dependency) {
    if (! is_array($dependency) || ! isset($dependency['license'])) {
        continue;
    }

    // Composer may return a string or an array for the license field.
    $licenses = (array) $dependency['license'];
    $licenses = array_values(array_filter(array_map('trim', $licenses)));

    if ($licenses === []) {
        $unknown[] = $name;

        continue;
    }

    $accepted = false;

    foreach ($licenses as $license) {
        // Composer returns an array for dual licensing; when it returns a single
        // string it may still be comma separated. Split on commas only — splitting
        // on "or" would break SPDX identifiers such as LGPL-2.1-or-later.
        foreach (preg_split('/\s*,\s*/', $license) as $option) {
            if (in_array($option, $allowlist, true)) {
                $accepted = true;
                break 2;
            }
        }
    }

    if (! $accepted) {
        $violations[$name] = implode(', ', $licenses);
    }
}

if ($unknown !== []) {
    fwrite(STDERR, "Dependencies with no declared license:\n  ".implode("\n  ", $unknown)."\n");
}

if ($violations !== []) {
    fwrite(STDERR, "Dependencies outside the allowlist:\n");
    foreach ($violations as $name => $license) {
        fwrite(STDERR, "  {$name}: {$license}\n");
    }
    fwrite(STDERR, "Update docs/OSS-POLICY.md and this allowlist together if the license is acceptable.\n");
}

if ($unknown !== [] || $violations !== []) {
    exit(1);
}

echo 'License check passed for '.count($dependencies)." production dependencies.\n";
