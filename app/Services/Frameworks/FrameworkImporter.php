<?php

namespace App\Services\Frameworks;

/**
 * A framework importer reads a catalog definition and writes a standard and its
 * controls. Unlike a seeder it receives the catalog entry, so one class can
 * serve many bundles that share a data format.
 */
interface FrameworkImporter
{
    /**
     * @param  array<string, mixed>  $definition
     * @return int Number of controls now attached to the standard
     */
    public function import(string $code, array $definition): int;
}
