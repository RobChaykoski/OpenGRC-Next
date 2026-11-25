<?php

namespace App\Services\Evidence\Pipelock;

use InvalidArgumentException;

/**
 * Reproduce Pipelock's canonical action-record JSON (receipt version 1).
 *
 * Pipelock signs SHA-256(canonical JSON of action_record), then Ed25519-signs
 * the 32-byte digest. The canonical form is Go's json.Marshal of the frozen
 * actionRecordCanonicalV1 struct: declaration field order, omitempty, compact
 * output, HTML-safe escaping of <, >, &, U+2028 and U+2029, and rejection of
 * unknown fields.
 *
 * Session-control records are lifecycle records rather than action decisions;
 * this importer rejects them rather than guessing their nested group binding.
 */
final class PipelockActionCanonicalizer
{
    /**
     * @param  array<string, mixed>  $action
     */
    public function canonical(array $action): string
    {
        return $this->encodeObject($action, $this->fields());
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function fields(): array
    {
        return [
            'version' => ['type' => 'int'],
            'action_id' => ['type' => 'string'],
            'parent_action_id' => ['type' => 'string', 'omitempty' => true],
            'action_type' => ['type' => 'string'],
            'timestamp' => ['type' => 'timestamp'],
            'principal' => ['type' => 'string'],
            'actor' => ['type' => 'string'],
            'delegation_chain' => ['type' => 'string_array', 'nullable' => true],
            'target' => ['type' => 'string'],
            'intent' => ['type' => 'string', 'omitempty' => true],
            'data_classes_in' => ['type' => 'string_array', 'omitempty' => true],
            'data_classes_out' => ['type' => 'string_array', 'omitempty' => true],
            'side_effect_class' => ['type' => 'string'],
            'reversibility' => ['type' => 'string'],
            'policy_hash' => ['type' => 'string'],
            'verdict' => ['type' => 'string'],
            'decision_phase' => ['type' => 'string', 'omitempty' => true],
            'defer_id' => ['type' => 'string', 'omitempty' => true],
            'resolution_policy' => ['type' => 'string', 'omitempty' => true],
            'resolution_source' => ['type' => 'string', 'omitempty' => true],
            'session_id' => ['type' => 'string', 'omitempty' => true],
            'session_id_original' => ['type' => 'string', 'omitempty' => true],
            'session_taint_level' => ['type' => 'string', 'omitempty' => true],
            'session_contaminated' => ['type' => 'bool', 'omitempty' => true],
            'recent_taint_sources' => ['type' => 'array_object', 'fields' => $this->taintSourceFields(), 'omitempty' => true],
            'session_task_id' => ['type' => 'string', 'omitempty' => true],
            'session_task_label' => ['type' => 'string', 'omitempty' => true],
            'authority_kind' => ['type' => 'string', 'omitempty' => true],
            'taint_decision' => ['type' => 'string', 'omitempty' => true],
            'taint_decision_reason' => ['type' => 'string', 'omitempty' => true],
            'task_override_applied' => ['type' => 'bool', 'omitempty' => true],
            'contract_winning_source' => ['type' => 'string', 'omitempty' => true],
            'contract_live_verdict' => ['type' => 'string', 'omitempty' => true],
            'contract_policy_sources' => ['type' => 'string_array', 'omitempty' => true],
            'contract_rule_id' => ['type' => 'string', 'omitempty' => true],
            'active_manifest_hash' => ['type' => 'string', 'omitempty' => true],
            'contract_hash' => ['type' => 'string', 'omitempty' => true],
            'contract_selector_id' => ['type' => 'string', 'omitempty' => true],
            'contract_generation' => ['type' => 'int', 'omitempty' => true],
            'transport' => ['type' => 'string'],
            'method' => ['type' => 'string', 'omitempty' => true],
            'layer' => ['type' => 'string', 'omitempty' => true],
            'pattern' => ['type' => 'string', 'omitempty' => true],
            'severity' => ['type' => 'string', 'omitempty' => true],
            'redaction' => ['type' => 'object', 'fields' => $this->redactionFields(), 'omitempty' => true],
            'shield' => ['type' => 'object', 'fields' => $this->shieldFields(), 'omitempty' => true],
            'request_id' => ['type' => 'string', 'omitempty' => true],
            'chain_prev_hash' => ['type' => 'string'],
            'chain_seq' => ['type' => 'int'],
            'run_nonce' => ['type' => 'string', 'omitempty' => true],
            'key_transition' => ['type' => 'object', 'fields' => $this->keyTransitionFields(), 'omitempty' => true],
            'session_control' => ['type' => 'session_control', 'omitempty' => true],
            'venue' => ['type' => 'string', 'omitempty' => true],
            'jurisdiction' => ['type' => 'string', 'omitempty' => true],
            'rulebook_id' => ['type' => 'string', 'omitempty' => true],
            'remedy_class' => ['type' => 'string', 'omitempty' => true],
            'contestation_window' => ['type' => 'string', 'omitempty' => true],
            'precedent_refs' => ['type' => 'string_array', 'omitempty' => true],
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function taintSourceFields(): array
    {
        return [
            'url' => ['type' => 'string'],
            'kind' => ['type' => 'string'],
            'level' => ['type' => 'string'],
            'timestamp' => ['type' => 'timestamp'],
            'receipt_id' => ['type' => 'string', 'omitempty' => true],
            'match_reason' => ['type' => 'string', 'omitempty' => true],
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function redactionFields(): array
    {
        return [
            'profile' => ['type' => 'string', 'omitempty' => true],
            'provider' => ['type' => 'string', 'omitempty' => true],
            'parser' => ['type' => 'string', 'omitempty' => true],
            'total_redactions' => ['type' => 'int', 'omitempty' => true],
            'by_class' => ['type' => 'map_int', 'omitempty' => true],
            'cache_boundary_kept' => ['type' => 'bool', 'omitempty' => true],
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function shieldFields(): array
    {
        return [
            'pipeline' => ['type' => 'string', 'omitempty' => true],
            'total_rewrites' => ['type' => 'int', 'omitempty' => true],
            'extension_probes' => ['type' => 'int', 'omitempty' => true],
            'tracking_beacons' => ['type' => 'int', 'omitempty' => true],
            'agent_traps' => ['type' => 'int', 'omitempty' => true],
            'fingerprint_shim_injected' => ['type' => 'bool', 'omitempty' => true],
            'svg_foreign_objects' => ['type' => 'int', 'omitempty' => true],
            'svg_event_handlers' => ['type' => 'int', 'omitempty' => true],
            'svg_external_references' => ['type' => 'int', 'omitempty' => true],
            'svg_hidden_text' => ['type' => 'int', 'omitempty' => true],
            'svg_animation_injections' => ['type' => 'int', 'omitempty' => true],
            'body_bytes' => ['type' => 'int', 'omitempty' => true],
            'scanned_bytes' => ['type' => 'int', 'omitempty' => true],
            'partial' => ['type' => 'bool', 'omitempty' => true],
            'adaptive_signals_recorded' => ['type' => 'int', 'omitempty' => true],
            'adaptive_signal_max_per_body' => ['type' => 'int', 'omitempty' => true],
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function keyTransitionFields(): array
    {
        return [
            'prior_signer_key' => ['type' => 'string'],
            'prior_chain_seq' => ['type' => 'int'],
            'prior_chain_hash' => ['type' => 'string'],
        ];
    }

    /**
     * @param  array<string, mixed>  $value
     * @param  array<string, array<string, mixed>>  $fields
     */
    private function encodeObject(array $value, array $fields): string
    {
        $unknown = array_diff(array_keys($value), array_keys($fields));

        if ($unknown !== []) {
            throw new InvalidArgumentException('Unknown Pipelock action fields: '.implode(', ', $unknown));
        }

        $parts = [];

        foreach ($fields as $name => $schema) {
            $present = array_key_exists($name, $value);
            $raw = $present ? $value[$name] : null;

            if (($schema['type'] ?? null) === 'session_control') {
                if ($present && $raw !== null) {
                    throw new InvalidArgumentException('Pipelock session-control receipts are not supported by this importer.');
                }

                continue;
            }

            if ($present && $this->isOmitted($raw, $schema)) {
                continue;
            }

            if (! $present && ($schema['omitempty'] ?? false)) {
                continue;
            }

            $encoded = $this->encodeValue($present ? $raw : $this->defaultFor($schema), $schema);
            $parts[] = $this->encodeString($name).':'.$encoded;
        }

        return '{'.implode(',', $parts).'}';
    }

    /**
     * @param  array<string, mixed>  $schema
     */
    private function encodeValue(mixed $value, array $schema): string
    {
        return match ($schema['type'] ?? null) {
            'int' => is_int($value) && $value >= 0 ? (string) $value : throw new InvalidArgumentException('Expected a non-negative integer.'),
            'string' => is_string($value) ? $this->encodeString($value) : throw new InvalidArgumentException('Expected a string.'),
            'bool' => is_bool($value) ? ($value ? 'true' : 'false') : throw new InvalidArgumentException('Expected a boolean.'),
            'timestamp' => $this->encodeTimestamp($value),
            'string_array' => $value === null && ($schema['nullable'] ?? false)
                ? 'null'
                : $this->encodeStringArray($value),
            'map_int' => $this->encodeIntMap($value),
            'array_object' => $this->encodeObjectArray($value, $schema['fields'] ?? []),
            'object' => is_array($value) ? $this->encodeObject($value, $schema['fields'] ?? []) : throw new InvalidArgumentException('Expected an object.'),
            default => throw new InvalidArgumentException('Unsupported Pipelock field type.'),
        };
    }

    /**
     * @param  array<string, mixed>  $schema
     */
    private function defaultFor(array $schema): mixed
    {
        if (($schema['type'] ?? null) === 'string_array' && ($schema['nullable'] ?? false)) {
            return null;
        }

        return match ($schema['type'] ?? null) {
            'int' => 0,
            'bool' => false,
            'string' => '',
            'string_array' => [],
            'map_int' => [],
            'array_object' => [],
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $schema
     */
    private function isOmitted(mixed $value, array $schema): bool
    {
        if (! ($schema['omitempty'] ?? false)) {
            return false;
        }

        if ($value === null) {
            return true;
        }

        return match ($schema['type'] ?? null) {
            'string' => $value === '',
            'bool' => $value === false,
            'int' => $value === 0,
            'string_array', 'array_object', 'map_int' => $value === [],
            default => $value === null,
        };
    }

    private function encodeTimestamp(mixed $value): string
    {
        if (! is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d{1,9})?Z$/', $value) !== 1) {
            throw new InvalidArgumentException('Expected an RFC 3339 UTC timestamp.');
        }

        return $this->encodeString($value);
    }

    private function encodeStringArray(mixed $value): string
    {
        if (! is_array($value) || ! array_is_list($value)) {
            throw new InvalidArgumentException('Expected an array of strings.');
        }

        $parts = [];

        foreach ($value as $item) {
            if (! is_string($item)) {
                throw new InvalidArgumentException('Expected an array of strings.');
            }

            $parts[] = $this->encodeString($item);
        }

        return '['.implode(',', $parts).']';
    }

    private function encodeIntMap(mixed $value): string
    {
        if (! is_array($value)) {
            throw new InvalidArgumentException('Expected a string-to-integer map.');
        }

        ksort($value, SORT_STRING);
        $parts = [];

        foreach ($value as $key => $item) {
            if (! is_string($key) || ! is_int($item) || $item < 0) {
                throw new InvalidArgumentException('Expected a string-to-integer map.');
            }

            $parts[] = $this->encodeString($key).':'.(string) $item;
        }

        return '{'.implode(',', $parts).'}';
    }

    /**
     * @param  array<string, array<string, mixed>>  $fields
     */
    private function encodeObjectArray(mixed $value, array $fields): string
    {
        if (! is_array($value) || ! array_is_list($value)) {
            throw new InvalidArgumentException('Expected an array of objects.');
        }

        $parts = [];

        foreach ($value as $item) {
            if (! is_array($item)) {
                throw new InvalidArgumentException('Expected an array of objects.');
            }

            $parts[] = $this->encodeObject($item, $fields);
        }

        return '['.implode(',', $parts).']';
    }

    private function encodeString(string $value): string
    {
        $encoded = json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return str_replace(
            ['<', '>', '&', "\u{2028}", "\u{2029}"],
            ['\\u003c', '\\u003e', '\\u0026', '\\u2028', '\\u2029'],
            $encoded,
        );
    }
}
