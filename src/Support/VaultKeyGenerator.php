<?php

declare(strict_types=1);

namespace IllumaLaw\VaultCipher\Support;

/**
 * Class VaultKeyGenerator
 *
 * Generates cryptographically secure encryption keys for use with the vault cipher.
 * Returns base64-encoded 32-byte keys (AES-256 compatible).
 */
final class VaultKeyGenerator
{
    /**
     * Generate a new encryption key.
     *
     * Returns a base64-encoded string representing 32 random bytes,
     * suitable for use as an AES-256 encryption key.
     */
    public static function generate(): string
    {
        return base64_encode(random_bytes(32));
    }
}
