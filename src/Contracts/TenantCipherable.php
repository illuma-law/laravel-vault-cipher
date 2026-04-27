<?php

declare(strict_types=1);

namespace IllumaLaw\VaultCipher\Contracts;

interface TenantCipherable
{
    public function getTenantIdForCipher(): int|string|null;
}
