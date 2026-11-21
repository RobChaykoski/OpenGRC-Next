<?php

namespace App\Support\Access;

use App\Enums\AccessRuleType;

/**
 * Normalisation and matching for access-rule values.
 *
 * IP rules accept a single address or a CIDR block. Domain rules accept an
 * email domain such as `acme.com` and an explicit wildcard `*.acme.com`.
 * Wildcards are opt-in: `acme.com` does not match `sub.acme.com`.
 */
final class AccessRuleValue
{
    public static function normalize(AccessRuleType|string $type, string $value): string
    {
        $type = $type instanceof AccessRuleType ? $type : AccessRuleType::from($type);
        $value = trim($value);

        return match ($type) {
            AccessRuleType::Ip => self::normalizeIp($value),
            AccessRuleType::EmailDomain => self::normalizeDomain($value),
        };
    }

    public static function isValid(AccessRuleType|string $type, string $value): bool
    {
        try {
            $normalised = self::normalize($type, $value);
        } catch (\Throwable) {
            return false;
        }

        return match ($type instanceof AccessRuleType ? $type : AccessRuleType::from($type)) {
            AccessRuleType::Ip => self::isValidIp($normalised),
            AccessRuleType::EmailDomain => self::isValidDomain($normalised),
        };
    }

    public static function matchesIp(string $ip, string $rule): bool
    {
        $ip = trim($ip);
        $rule = trim($rule);

        if ($ip === '' || $rule === '') {
            return false;
        }

        if (! str_contains($rule, '/')) {
            $left = @inet_pton($ip);
            $right = @inet_pton($rule);

            return $left !== false && $right !== false && $left === $right;
        }

        [$subnet, $prefix] = array_pad(explode('/', $rule, 2), 2, null);

        if ($subnet === null || ! is_numeric($prefix)) {
            return false;
        }

        $ipBinary = @inet_pton($ip);
        $subnetBinary = @inet_pton($subnet);

        if ($ipBinary === false || $subnetBinary === false || strlen($ipBinary) !== strlen($subnetBinary)) {
            return false;
        }

        $prefix = (int) $prefix;
        $maxBits = strlen($ipBinary) * 8;

        if ($prefix < 0 || $prefix > $maxBits) {
            return false;
        }

        $fullBytes = intdiv($prefix, 8);
        $remainingBits = $prefix % 8;

        if ($fullBytes > 0 && substr($ipBinary, 0, $fullBytes) !== substr($subnetBinary, 0, $fullBytes)) {
            return false;
        }

        if ($remainingBits === 0) {
            return true;
        }

        $mask = (0xFF << (8 - $remainingBits)) & 0xFF;

        return (ord($ipBinary[$fullBytes]) & $mask) === (ord($subnetBinary[$fullBytes]) & $mask);
    }

    public static function matchesEmailDomain(string $email, string $rule): bool
    {
        $domain = self::emailDomain($email);
        $rule = self::normalizeDomain($rule);

        if ($domain === null || $rule === '') {
            return false;
        }

        if (str_starts_with($rule, '*.')) {
            $suffix = substr($rule, 2);

            return $domain !== $suffix && str_ends_with($domain, '.'.$suffix);
        }

        return $domain === $rule;
    }

    public static function emailDomain(string $email): ?string
    {
        $email = trim($email);

        if (! str_contains($email, '@')) {
            return null;
        }

        $domain = substr(strrchr($email, '@') ?: '', 1);
        $domain = self::normalizeDomain($domain);

        return self::isValidDomain($domain) ? $domain : null;
    }

    private static function normalizeIp(string $value): string
    {
        if (! str_contains($value, '/')) {
            $binary = @inet_pton($value);

            return $binary === false ? $value : (string) inet_ntop($binary);
        }

        [$subnet, $prefix] = array_pad(explode('/', $value, 2), 2, null);
        $binary = @inet_pton((string) $subnet);

        if ($binary === false || $prefix === null || ! is_numeric($prefix)) {
            return $value;
        }

        $prefix = (int) $prefix;
        $maxBits = strlen($binary) * 8;

        if ($prefix < 0 || $prefix > $maxBits) {
            return $value;
        }

        // Keep the network address, not a host address inside the block.
        $bytes = intdiv($prefix, 8);
        $remaining = $prefix % 8;
        $network = substr($binary, 0, $bytes);

        if ($remaining > 0) {
            $mask = (0xFF << (8 - $remaining)) & 0xFF;
            $network .= chr(ord($binary[$bytes]) & $mask);
            $bytes++;
        }

        $network = str_pad($network, strlen($binary), "\0");

        return inet_ntop($network).'/'.$prefix;
    }

    private static function isValidIp(string $value): bool
    {
        if (! str_contains($value, '/')) {
            return filter_var($value, FILTER_VALIDATE_IP) !== false;
        }

        [$subnet, $prefix] = array_pad(explode('/', $value, 2), 2, null);

        if ($subnet === null || $prefix === null || ! is_numeric($prefix)) {
            return false;
        }

        $binary = @inet_pton($subnet);

        if ($binary === false) {
            return false;
        }

        $prefix = (int) $prefix;

        return $prefix >= 0 && $prefix <= strlen($binary) * 8;
    }

    private static function normalizeDomain(string $value): string
    {
        $value = trim($value);
        $value = ltrim($value, '@');
        $value = strtolower(rtrim($value, '.'));

        if (str_contains($value, '@')) {
            $value = (string) substr(strrchr($value, '@'), 1);
        }

        if (function_exists('idn_to_ascii') && ! str_starts_with($value, '*.') && preg_match('/[^\x20-\x7E]/', $value) === 1) {
            $ascii = idn_to_ascii($value, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46);

            if (is_string($ascii) && $ascii !== '') {
                $value = strtolower($ascii);
            }
        }

        return $value;
    }

    private static function isValidDomain(string $value): bool
    {
        if (str_contains($value, '*') && ! str_starts_with($value, '*.')) {
            return false;
        }

        $value = $value === '' ? '' : ltrim($value, '*.');
        $labels = explode('.', $value);

        if ($value === '' || count($labels) < 2 || strlen($value) > 190) {
            return false;
        }

        foreach ($labels as $label) {
            if ($label === '' || strlen($label) > 63) {
                return false;
            }

            if (preg_match('/^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$/', $label) !== 1) {
                return false;
            }
        }

        return true;
    }
}
