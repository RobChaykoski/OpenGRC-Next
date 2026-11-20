<?php

namespace App\Services\SupplyChain;

use App\Models\SoftwareComponent;

/**
 * Minimal purl handling for OSV queries.
 *
 * The purl type is mapped to an OSV ecosystem; unsupported types are skipped
 * rather than guessed. A missing version is also skipped: OSV cannot answer a
 * versionless query.
 */
class Purl
{
    /** @var array<string, string> */
    private const ECOSYSTEMS = [
        'composer' => 'Packagist',
        'npm' => 'npm',
        'pypi' => 'PyPI',
        'maven' => 'Maven',
        'nuget' => 'NuGet',
        'gem' => 'RubyGems',
        'golang' => 'Go',
        'cargo' => 'crates.io',
        'hex' => 'Hex',
        'pub' => 'Pub',
        'swift' => 'SwiftURL',
        'githubactions' => 'GitHub Actions',
    ];

    /** @var array<int, string> */
    private const NAMESPACE_TYPES = ['npm', 'composer', 'maven', 'golang', 'githubactions'];

    public static function ecosystem(?string $purl): ?string
    {
        $type = self::type($purl);

        return $type === null ? null : (self::ECOSYSTEMS[$type] ?? null);
    }

    /**
     * @return array{package: array{name: string, ecosystem: string}, version: string}|null
     */
    public static function osvQuery(SoftwareComponent $component): ?array
    {
        $parsed = self::parse($component->purl);
        $ecosystem = $parsed === null ? null : (self::ECOSYSTEMS[$parsed['type']] ?? null);

        if ($parsed === null || $ecosystem === null) {
            return null;
        }

        $version = $parsed['version'] ?? $component->version;

        if (! is_string($version) || trim($version) === '') {
            return null;
        }

        $name = self::queryName($parsed);

        if ($name === null) {
            return null;
        }

        return [
            'package' => [
                'name' => $name,
                'ecosystem' => $ecosystem,
            ],
            'version' => $version,
        ];
    }

    private static function type(?string $purl): ?string
    {
        return self::parse($purl)['type'] ?? null;
    }

    /**
     * @return array{type: string, namespace: string|null, name: string, version: string|null}|null
     */
    private static function parse(?string $purl): ?array
    {
        if (! is_string($purl) || ! str_starts_with($purl, 'pkg:')) {
            return null;
        }

        if (preg_match('#^pkg:([a-zA-Z0-9.+-]+)/(?:(.+)/)?([^/@?]+)(?:@([^?]+))?#', $purl, $matches) !== 1) {
            return null;
        }

        return [
            'type' => strtolower($matches[1]),
            'namespace' => $matches[2] !== '' ? urldecode($matches[2]) : null,
            'name' => urldecode($matches[3]),
            'version' => isset($matches[4]) && $matches[4] !== '' ? urldecode($matches[4]) : null,
        ];
    }

    /**
     * @param  array{type: string, namespace: string|null, name: string, version: string|null}  $parsed
     */
    private static function queryName(array $parsed): ?string
    {
        $namespace = $parsed['namespace'];
        $name = $parsed['name'];

        if ($namespace === null || ! in_array($parsed['type'], self::NAMESPACE_TYPES, true)) {
            return $name;
        }

        return match ($parsed['type']) {
            'maven' => $namespace.':'.$name,
            default => $namespace.'/'.$name,
        };
    }
}
