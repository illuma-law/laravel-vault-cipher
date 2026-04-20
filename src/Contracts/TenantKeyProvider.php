<?php

declare(strict_types=1);

namespace IllumaLaw\VaultCipher\Contracts;

/**
 * Interface TenantKeyProvider
 *
 * Provides the encryption key for a given tenant.
 */
interface TenantKeyProvider
{
    public function getKey(int|string $tenantId): string;
}
