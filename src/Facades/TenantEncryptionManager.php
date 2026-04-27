<?php

declare(strict_types=1);

namespace IllumaLaw\VaultCipher\Facades;

use IllumaLaw\VaultCipher\TenantEncryptionManager as Manager;
use Illuminate\Support\Facades\Facade;

/**
 * @method static void forgetTenant(int|string $tenantId)
 * @method static \Illuminate\Encryption\Encrypter encrypterForTenant(int|string $tenantId)
 * @method static string encryptString(int|string $tenantId, string $value)
 * @method static string decryptString(int|string $tenantId, string $value)
 * @method static bool isEncryptedPayload(int|string $tenantId, string $value)
 * @method static void store(int|string $tenantId, string $path, string $contents, ?string $disk = null)
 * @method static string get(int|string $tenantId, string $path, ?string $disk = null)
 * @method static bool encryptExistingPath(int|string $tenantId, string $path, ?string $disk = null)
 * @method static string decryptToTempPath(int|string $tenantId, string $path, ?string $disk = null)
 * @method static \Ercsctt\FileEncryption\FileEncrypter fileEncrypterForTenant(int|string $tenantId)
 * @method static bool isChunkEncryptedPayload(string $contents)
 * @method static bool isChunkEncryptedFile(string $path)
 * @method static \Illuminate\Encryption\Encrypter encrypterForRawKey(string $rawKey)
 * @method static \Ercsctt\FileEncryption\FileEncrypter fileEncrypterForRawKey(string $rawKey)
 * @method static mixed usingDecryptedTempPath(int|string $tenantId, string $path, callable $callback, ?string $disk = null)
 *
 * @see Manager
 */
class TenantEncryptionManager extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return Manager::class;
    }
}
