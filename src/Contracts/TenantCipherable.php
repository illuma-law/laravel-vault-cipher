<?php

declare(strict_types=1);

namespace IllumaLaw\VaultCipher\Contracts;

/**
 * Interface TenantCipherable
 *
 * Defines a model that can provide a tenant ID for encryption.
 */
interface TenantCipherable
{
    public function getTenantIdForCipher(): int|string|null;
}
