<?php

declare(strict_types=1);

namespace IllumaLaw\VaultCipher\Casts;

use IllumaLaw\VaultCipher\Contracts\TenantCipherable;
use IllumaLaw\VaultCipher\Facades\TenantEncryptionManager as TenantEncryption;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

/**
 * @implements CastsAttributes<mixed, mixed>
 */
class TenantEncrypted implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        if (! is_string($value) || $value === '') {
            return $value;
        }

        $tenantId = $this->resolveTenantId($model);

        if ($tenantId === null) {
            return $value;
        }

        if (! TenantEncryption::isEncryptedPayload($tenantId, $value)) {
            return $value;
        }

        return TenantEncryption::decryptString($tenantId, $value);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        if ($value === null || $value === '') {
            return $value;
        }

        if (! is_string($value)) {
            $value = is_scalar($value) ? (string) $value : '';
        }

        $tenantId = $this->resolveTenantId($model);

        if ($tenantId === null) {
            return $value;
        }

        if (TenantEncryption::isEncryptedPayload($tenantId, $value)) {
            return $value;
        }

        return TenantEncryption::encryptString($tenantId, $value);
    }

    /**
     * @throws RuntimeException
     */
    protected function resolveTenantId(Model $model): int|string|null
    {
        if ($model instanceof TenantCipherable) {
            return $model->getTenantIdForCipher();
        }

        if (isset($model->tenant_id)) {
            $tenantId = $model->tenant_id;

            return is_string($tenantId) || is_int($tenantId) ? $tenantId : null;
        }

        throw new RuntimeException(sprintf(
            'Model [%s] must implement [%s] to use TenantEncrypted casts.',
            $model::class,
            TenantCipherable::class,
        ));
    }
}
