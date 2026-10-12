<?php

namespace BitApps\Pi\Helpers;

// Prevent direct script access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Validates and matches IP addresses against an allowlist.
 *
 * Supports single IPv4/IPv6 addresses and CIDR ranges (e.g. 10.0.0.0/8, 2001:db8::/32).
 */
final class IpAllowlist
{
    /**
     * Whether the entry is a valid single IP or CIDR range.
     */
    public static function isValidEntry(string $entry): bool
    {
        $entry = trim($entry);

        if ($entry === '') {
            return false;
        }

        if (!str_contains($entry, '/')) {
            return (bool) rest_is_ip_address($entry);
        }

        [$subnet, $bits] = explode('/', $entry, 2);

        if (!ctype_digit($bits)) {
            return false;
        }

        $bits = (int) $bits;

        // filter_var flags (not rest_is_ip_address) because the max prefix length depends on the IP family.
        if (filter_var($subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return $bits <= 32;
        }

        if (filter_var($subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            return $bits <= 128;
        }

        return false;
    }

    /**
     * Whether the client IP matches any entry in the allowlist.
     *
     * @param string   $clientIp  the incoming request IP
     * @param string[] $allowlist single IPs and/or CIDR ranges
     */
    public static function matches(string $clientIp, array $allowlist): bool
    {
        foreach (self::packedForms($clientIp) as $clientPacked) {
            foreach ($allowlist as $entry) {
                $isMatch = !str_contains($entry, '/')
                    ? @inet_pton($entry) === $clientPacked
                    : self::cidrMatch($clientPacked, $entry);

                if ($isMatch) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * The packed forms of the client IP to test against the allowlist.
     *
     * Dual-stack servers report IPv4 callers as IPv4-mapped IPv6 in REMOTE_ADDR
     * (::ffff:203.0.113.5), which packs to 16 bytes and so can never equal the
     * 4 bytes an IPv4 allowlist entry packs to. Both forms are returned so an
     * allowlist written either way still matches.
     *
     * @return string[] packed addresses, empty when the IP is unparseable
     */
    private static function packedForms(string $clientIp): array
    {
        $packed = @inet_pton(trim($clientIp));

        if ($packed === false) {
            return [];
        }

        $forms = [$packed];

        // ::ffff:0:0/96 — the trailing 4 bytes are the embedded IPv4 address.
        if (str_starts_with($packed, "\0\0\0\0\0\0\0\0\0\0\xFF\xFF")) {
            $forms[] = substr($packed, 12);
        }

        return $forms;
    }

    /**
     * Whether the packed client IP falls inside the CIDR range.
     *
     * Entries are validated by isValidEntry() before being saved, so this only
     * guards against what validation can't rule out: an entry whose IP family
     * (IPv4 vs IPv6) differs from the client's.
     */
    private static function cidrMatch(string $clientPacked, string $cidr): bool
    {
        [$subnet, $bits] = explode('/', $cidr, 2);

        $subnetPacked = @inet_pton($subnet);
        $bits = (int) $bits;

        if ($subnetPacked === false || \strlen($subnetPacked) !== \strlen($clientPacked) || $bits > \strlen($subnetPacked) * 8) {
            return false;
        }

        $wholeBytes = intdiv($bits, 8);
        $remainder = $bits % 8;

        if (substr($subnetPacked, 0, $wholeBytes) !== substr($clientPacked, 0, $wholeBytes)) {
            return false;
        }

        if ($remainder === 0) {
            return true;
        }

        // Compare only the leading bits of the boundary byte.
        $mask = 0xFF << (8 - $remainder) & 0xFF;

        return (\ord($subnetPacked[$wholeBytes]) & $mask) === (\ord($clientPacked[$wholeBytes]) & $mask);
    }
}
