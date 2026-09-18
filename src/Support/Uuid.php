<?php declare(strict_types=1);

namespace Digibit\Audit\Support;

/**
 * Minimal UUID v4 generator, shared by anything in the audit package that
 * needs an opaque unique identifier (transaction IDs, event IDs). Not
 * cryptographically significant — it only needs to be unique enough to
 * correlate and deduplicate audit records.
 */
final class Uuid
{
    public static function v4(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }
}
