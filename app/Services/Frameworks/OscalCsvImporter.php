<?php

namespace App\Services\Frameworks;

use App\Enums\ControlCategory;
use App\Enums\ControlEnforcementCategory;
use App\Enums\ControlType;
use App\Http\Controllers\HelperController;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use League\Csv\Reader;
use League\Csv\Statement;
use RuntimeException;

/**
 * Import a framework from a CSV with Code,Title,Description,Discussion columns.
 *
 * The import is idempotent: an existing standard with controls is left alone, so
 * running `opengrc:framework-import --all` cannot duplicate a catalogue or
 * detach implementations from their controls.
 */
class OscalCsvImporter implements FrameworkImporter
{
    public function import(string $code, array $definition): int
    {
        $relative = $definition['data'] ?? null;

        if (! is_string($relative) || $relative === '') {
            throw new InvalidArgumentException("Framework [{$code}] has no data file.");
        }

        $path = base_path($relative);

        if (! is_file($path)) {
            throw new RuntimeException("Data file not found: {$relative}");
        }

        $existing = DB::table('standards')->where('code', $code)->first();

        if ($existing !== null) {
            return DB::table('controls')
                ->where('standard_id', $existing->id)
                ->whereNull('deleted_at')
                ->count();
        }

        $csv = Reader::createFromPath($path, 'r');
        $csv->setHeaderOffset(0);
        $rows = iterator_to_array((new Statement)->process($csv));
        $now = now();

        return DB::transaction(function () use ($code, $definition, $rows, $now): int {
            $standardId = DB::table('standards')->insertGetId([
                'name' => $definition['name'] ?? $code,
                'code' => $code,
                'authority' => $definition['authority'] ?? 'Unknown',
                'reference_url' => $definition['reference_url'] ?? null,
                'description' => $definition['description'] ?? ($definition['name'] ?? $code),
                'status' => $definition['status'] ?? 'Draft',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $batch = [];

            foreach ($rows as $row) {
                $controlCode = trim((string) ($row['Code'] ?? ''));
                $title = trim((string) ($row['Title'] ?? ''));

                if ($controlCode === '' || $title === '') {
                    continue;
                }

                $description = trim((string) ($row['Description'] ?? ''));
                $discussion = trim((string) ($row['Discussion'] ?? ''));

                $batch[] = [
                    'standard_id' => $standardId,
                    'code' => $controlCode,
                    'title' => $title,
                    'description' => $description === '' ? '' : HelperController::linesToParagraphs($description, 'control-description-text'),
                    'discussion' => $discussion === '' ? '' : HelperController::linesToParagraphs($discussion, 'control-discussion-text'),
                    'type' => ControlType::OTHER->value,
                    'category' => ControlCategory::UNKNOWN->value,
                    'enforcement' => ControlEnforcementCategory::MANDATORY->value,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                if (count($batch) >= 200) {
                    DB::table('controls')->insert($batch);
                    $batch = [];
                }
            }

            if ($batch !== []) {
                DB::table('controls')->insert($batch);
            }

            return DB::table('controls')->where('standard_id', $standardId)->count();
        });
    }
}
