<?php

declare(strict_types=1);

namespace IllumaLaw\VaultCipher\Contracts;

interface TenantKeyProvider
{
    public function getKey(int|string $tenantId): string;
}
