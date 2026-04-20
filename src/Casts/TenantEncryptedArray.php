<?php

declare(strict_types=1);

namespace IllumaLaw\VaultCipher\Casts;

use IllumaLaw\VaultCipher\Contracts\TenantCipherable;
use IllumaLaw\VaultCipher\Facades\TenantEncryptionManager as TenantEncryption;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

/**
 * Class TenantEncryptedArray
 *
 * Eloquent cast for tenant-aware array encryption (stored as JSON).
 *
 * @implements CastsAttributes<mixed, mixed>
 */
class TenantEncryptedArray implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        if ($value === null) {
            return null;
        }

        if (is_array($value)) {
            return $value;
        }

        if (! is_string($value) || $value === '') {
            return [];
        }

        $tenantId = $this->resolveTenantId($model);

        if ($tenantId !== null && TenantEncryption::isEncryptedPayload($tenantId, $value)) {
            $value = TenantEncryption::decryptString($tenantId, $value);
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        if ($value === null) {
            return null;
        }

        $tenantId = $this->resolveTenantId($model);

        if ($tenantId === null) {
            return json_encode($value, JSON_THROW_ON_ERROR);
        }

        if (is_string($value) && TenantEncryption::isEncryptedPayload($tenantId, $value)) {
            return $value;
        }

        $json = json_encode($value, JSON_THROW_ON_ERROR);

        return TenantEncryption::encryptString($tenantId, $json);
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
            'Model [%s] must implement [%s] to use TenantEncryptedArray casts.',
            $model::class,
            TenantCipherable::class,
        ));
    }
}
