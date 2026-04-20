<?php

declare(strict_types=1);

namespace IllumaLaw\VaultCipher\Support;

/**
 * Trait NormalizesEncryptionKeys
 *
 * Provides a helper method for taking a base64 encoded encryption key
 * (and optionally handling legacy serialized formatting) and decoding it
 * into a raw 32-byte string for use with the TenantKeyProvider contract.
 */
trait NormalizesEncryptionKeys
{
    protected function decodeBase64Key(string $key): string
    {
        $candidate = trim($key);

        $decodedCandidate = base64_decode($candidate, true);
        if (is_string($decodedCandidate) && strlen($decodedCandidate) === 32) {
            return $decodedCandidate;
        }

        // Handle legacy serialized strings
        $unserialized = @unserialize($candidate, ['allowed_classes' => false]);

        if (! is_string($unserialized)) {
            throw new \RuntimeException('Invalid encryption key format.');
        }

        $decodedUnserialized = base64_decode($unserialized, true);
        if (is_string($decodedUnserialized) && strlen($decodedUnserialized) === 32) {
            return $decodedUnserialized;
        }

        throw new \RuntimeException('Invalid encryption key format.');
    }
}
