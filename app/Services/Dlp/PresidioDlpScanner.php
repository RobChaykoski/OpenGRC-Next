<?php

namespace App\Services\Dlp;

use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Optional Presidio analyzer integration.
 *
 * Point PRESIDIO_URL at a local sidecar. Do not point it at a third-party
 * service: the text being scanned may itself be sensitive. If Presidio is
 * unavailable the native scanner still runs; this adapter never blocks a scan.
 */
final class PresidioDlpScanner implements DlpScanner
{
    public function scan(string $text): array
    {
        $url = config('dlp.presidio_url');

        if (! is_string($url) || trim($url) === '') {
            return [];
        }

        try {
            $response = Http::timeout((int) config('dlp.presidio_timeout', 5))
                ->acceptJson()
                ->post(rtrim($url, '/').'/analyze', [
                    'text' => $text,
                    'language' => 'en',
                    'score_threshold' => 0.5,
                ]);

            if (! $response->successful()) {
                logger()->warning('Presidio DLP scan failed.', ['status' => $response->status()]);

                return [];
            }

            $entities = $response->json();

            if (! is_array($entities)) {
                return [];
            }

            $counts = [];

            foreach ($entities as $entity) {
                if (! is_array($entity)) {
                    continue;
                }

                $definition = $this->definition((string) ($entity['entity_type'] ?? ''));

                if ($definition === null) {
                    continue;
                }

                $counts[$definition['type']] = ($counts[$definition['type']] ?? 0) + 1;
            }

            $matches = [];

            foreach ($counts as $type => $count) {
                $definition = $this->definitionForType($type);

                if ($definition !== null) {
                    $matches[] = new DlpMatch($definition['type'], $definition['label'], $definition['severity'], $count);
                }
            }

            return $matches;
        } catch (Throwable $exception) {
            logger()->warning('Presidio DLP scan failed.', ['error' => $exception->getMessage()]);

            return [];
        }
    }

    public function name(): string
    {
        return 'presidio';
    }

    public function isConfigured(): bool
    {
        $url = config('dlp.presidio_url');

        return is_string($url) && trim($url) !== '';
    }

    /**
     * @return array{type: string, label: string, severity: string}|null
     */
    private function definition(string $entityType): ?array
    {
        return match (strtoupper($entityType)) {
            'CREDIT_CARD', 'US_BANK_NUMBER', 'IBAN_CODE' => [
                'type' => 'personal',
                'label' => 'Payment or bank account number',
                'severity' => 'high',
            ],
            'US_SSN', 'US_PASSPORT', 'MEDICAL_LICENSE' => [
                'type' => 'special_category',
                'label' => 'Government or medical identifier',
                'severity' => 'critical',
            ],
            'EMAIL_ADDRESS', 'PHONE_NUMBER', 'PERSON', 'LOCATION' => [
                'type' => 'personal',
                'label' => 'Personal identifier',
                'severity' => 'medium',
            ],
            'CRYPTO' => [
                'type' => 'credential',
                'label' => 'Credential or crypto address',
                'severity' => 'critical',
            ],
            default => null,
        };
    }

    /**
     * @return array{type: string, label: string, severity: string}|null
     */
    private function definitionForType(string $type): ?array
    {
        return match ($type) {
            'credential' => ['type' => 'credential', 'label' => 'Credential', 'severity' => 'critical'],
            'special_category' => ['type' => 'special_category', 'label' => 'Special-category data', 'severity' => 'critical'],
            'personal' => ['type' => 'personal', 'label' => 'Personal identifier', 'severity' => 'medium'],
            default => null,
        };
    }
}
