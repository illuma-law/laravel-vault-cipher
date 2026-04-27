<?php

declare(strict_types=1);

namespace IllumaLaw\VaultCipher\Support;

final class VaultKeyGenerator
{
    public static function generate(): string
    {
        return base64_encode(random_bytes(32));
    }
}
