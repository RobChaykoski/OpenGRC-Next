<?php

/**
 * Convert a NIST OSCAL catalog (JSON) into the CSV shape OpenGRC imports:
 * Code,Title,Description,Discussion.
 *
 * NIST publishes SP 800-53, SP 800-53B, SP 800-171, SP 800-172, SP 800-218,
 * the Cybersecurity Framework and the AI RMF as OSCAL. The content is a work of
 * the US Government and is not subject to copyright in the United States
 * (17 U.S.C. 105); attribution is recorded in config/framework_catalog.php.
 *
 * Usage:
 *   php scripts/oscal-to-csv.php <catalog.json> <output.csv>
 *
 * The script is deterministic: the same OSCAL file produces the same CSV, so a
 * regenerated file can be reviewed as a diff.
 */
if ($argc < 3) {
    fwrite(STDERR, "Usage: php scripts/oscal-to-csv.php <catalog.json> <output.csv>\n");
    exit(1);
}

$input = $argv[1];
$output = $argv[2];

if (! is_file($input)) {
    fwrite(STDERR, "Input not found: {$input}\n");
    exit(1);
}

$document = json_decode((string) file_get_contents($input), true);

if (! is_array($document) || ! isset($document['catalog'])) {
    fwrite(STDERR, "The input is not an OSCAL catalog.\n");
    exit(1);
}

$catalog = $document['catalog'];
$rows = [];

walkControls($catalog['controls'] ?? [], $rows);

foreach ($catalog['groups'] ?? [] as $group) {
    walkControls($group['controls'] ?? [], $rows);
}

$handle = fopen($output, 'w');

if ($handle === false) {
    fwrite(STDERR, "Cannot write: {$output}\n");
    exit(1);
}

fputcsv($handle, ['Code', 'Title', 'Description', 'Discussion'], ',', '"', '');

$seen = [];

foreach ($rows as $row) {
    if (isset($seen[$row['code']])) {
        fwrite(STDERR, "Duplicate identifier {$row['code']}, keeping the first occurrence.\n");

        continue;
    }

    $seen[$row['code']] = true;
    fputcsv($handle, [$row['code'], $row['title'], $row['description'], $row['discussion']], ',', '"', '');
}

fclose($handle);

fwrite(STDOUT, sprintf("%s: %d control(s)\n", $output, count($seen)));

/**
 * @param  array<int, array<string, mixed>>  $controls
 * @param  array<int, array{code: string, title: string, description: string, discussion: string}>  $rows
 */
function walkControls(array $controls, array &$rows): void
{
    foreach ($controls as $control) {
        if (status($control) === 'withdrawn') {
            continue;
        }

        $params = params($control);
        $description = partsText($control, 'statement', $params);
        $discussion = partsText($control, 'guidance', $params);

        if ($description === '' && $discussion !== '') {
            $description = $discussion;
            $discussion = '';
        }

        $code = identifier($control);
        $title = trim((string) ($control['title'] ?? ''));

        if ($title === '' || strcasecmp($title, $code) === 0) {
            $title = headline($description, $code);
        }

        $rows[] = [
            'code' => clean($code),
            'title' => clean($title),
            'description' => $description,
            'discussion' => $discussion,
        ];

        walkControls($control['controls'] ?? [], $rows);
    }
}

/**
 * A readable title for a control whose OSCAL title is only its identifier.
 * The requirement text stays untouched in the description column; this is a
 * label for lists and pickers, not a rewrite of the requirement.
 */
function headline(string $description, string $fallback): string
{
    $first = trim((string) preg_split('/\R/', $description)[0]);
    $first = trim((string) preg_replace('/\s+/u', ' ', $first));

    if ($first === '') {
        return $fallback;
    }

    $sentence = preg_split('/(?<=[.!?])\s/', $first)[0] ?? $first;

    if (mb_strlen($sentence) <= 160) {
        return $sentence;
    }

    $trimmed = mb_substr($sentence, 0, 157);
    $space = mb_strrpos($trimmed, ' ');

    return rtrim($space === false ? $trimmed : mb_substr($trimmed, 0, $space)).'...';
}

/**
 * The published identifier where OSCAL carries one; otherwise derived from the
 * OSCAL id, which is stable and unique within the catalog.
 *
 * @param  array<string, mixed>  $control
 */
function identifier(array $control): string
{
    foreach ($control['props'] ?? [] as $property) {
        if (($property['name'] ?? '') !== 'label' || isset($property['class'])) {
            continue;
        }

        $label = trim((string) ($property['value'] ?? ''));

        if ($label !== '' && ! str_contains($label, ' ')) {
            return $label;
        }
    }

    return str_replace('_', ' ', trim((string) ($control['id'] ?? 'unknown')));
}

/**
 * @param  array<string, mixed>  $control
 */
function status(array $control): ?string
{
    foreach ($control['props'] ?? [] as $property) {
        if (($property['name'] ?? '') === 'status') {
            return strtolower((string) ($property['value'] ?? ''));
        }
    }

    return null;
}

/**
 * @param  array<string, mixed>  $control
 * @return array<string, string>
 */
function params(array $control): array
{
    $resolved = [];

    foreach ($control['params'] ?? [] as $param) {
        $id = (string) ($param['id'] ?? '');

        if ($id === '') {
            continue;
        }

        $resolved[$id] = parameterText($param);
    }

    return $resolved;
}

/**
 * Render an organisational defined parameter in the form NIST uses in print.
 *
 * @param  array<string, mixed>  $param
 */
function parameterText(array $param): string
{
    $select = $param['select'] ?? null;

    if (is_array($select) && ! empty($select['choice'])) {
        $choices = array_map(fn ($choice): string => trim((string) $choice), $select['choice']);
        $howMany = strtolower((string) ($select['how-many'] ?? 'one'));

        return sprintf('[Selection (%s): %s]', $howMany, implode('; ', $choices));
    }

    $label = trim((string) ($param['label'] ?? ''));

    return $label === ''
        ? '[Assignment: organization-defined value]'
        : '[Assignment: '.$label.']';
}

/**
 * Normalise line endings and drop control characters. A bare carriage return is
 * treated as a record separator by CSV readers, so leaving one in a quoted field
 * silently adds rows.
 */
function clean(string $text): string
{
    $text = str_replace(["\r\n", "\r"], "\n", $text);

    // Some OSCAL prose carries Markdown-style escaped quotes. Left in place they
    // make CSV writers and readers disagree about where a quoted field ends.
    $text = str_replace(['\\"', "\\'"], ['"', "'"], $text);

    return trim((string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $text));
}

/**
 * Text of every direct part with the given name, including nested items.
 *
 * @param  array<string, mixed>  $node
 * @param  array<string, string>  $params
 */
function partsText(array $node, string $name, array $params): string
{
    $chunks = [];

    foreach ($node['parts'] ?? [] as $part) {
        if (($part['name'] ?? '') !== $name) {
            continue;
        }

        $text = partText($part, $params);

        if ($text !== '') {
            $chunks[] = $text;
        }
    }

    return implode("\n\n", $chunks);
}

/**
 * @param  array<string, mixed>  $part
 * @param  array<string, string>  $params
 */
function partText(array $part, array $params): string
{
    $lines = [];
    $prose = clean((string) ($part['prose'] ?? ''));

    if ($prose !== '') {
        $lines[] = resolveParams($prose, $params);
    }

    foreach ($part['parts'] ?? [] as $child) {
        $text = partText($child, $params);

        if ($text === '') {
            continue;
        }

        $label = label($child);

        if ($label !== '') {
            $text = $label.' '.$text;
        }

        $lines[] = $text;
    }

    return implode("\n", $lines);
}

/**
 * @param  array<string, mixed>  $part
 */
function label(array $part): string
{
    foreach ($part['props'] ?? [] as $property) {
        if (($property['name'] ?? '') === 'label') {
            return trim((string) ($property['value'] ?? ''));
        }
    }

    return '';
}

/**
 * @param  array<string, string>  $params
 */
function resolveParams(string $prose, array $params): string
{
    return (string) preg_replace_callback(
        '/\{\{\s*insert:\s*param,\s*([^}\s]+)\s*\}\}/',
        fn (array $match): string => $params[$match[1]] ?? '[Assignment: organization-defined value]',
        $prose,
    );
}
