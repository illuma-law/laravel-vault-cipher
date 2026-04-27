<?php

declare(strict_types=1);

namespace IllumaLaw\VaultCipher\Support;

trait NormalizesEncryptionKeys
{
    protected function decodeBase64Key(string $key): string
    {
        $candidate = trim($key);

        $decodedCandidate = base64_decode($candidate, true);
        if (is_string($decodedCandidate) && strlen($decodedCandidate) === 32) {
            return $decodedCandidate;
        }

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
