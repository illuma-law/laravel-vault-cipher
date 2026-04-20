# Laravel Vault Cipher

[![Latest Version on Packagist](https://img.shields.io/packagist/v/illuma-law/laravel-vault-cipher.svg?style=flat-square)](https://packagist.org/packages/illuma-law/laravel-vault-cipher)
[![GitHub Tests Action Action Status](https://img.shields.io/github/actions/workflow/status/illuma-law/laravel-vault-cipher/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/illuma-law/laravel-vault-cipher/actions/workflows/run-tests.yml)
[![Total Downloads](https://img.shields.io/packagist/dt/illuma-law/laravel-vault-cipher.svg?style=flat-square)](https://packagist.org/packages/illuma-law/laravel-vault-cipher)

Tenant-aware streaming encryption for Laravel applications.

In multi-tenant (B2B) applications, sharing a single application-wide encryption key (the standard Laravel `APP_KEY`) creates a massive blast radius if compromised. Vault Cipher solves this by encrypting each tenant's data with their own unique encryption key.

## Features

- **Tenant-Specific Keys:** Encrypt and decrypt data dynamically using unique keys per tenant.
- **File Streaming Encryption:** Securely encrypt large files (e.g., PDFs, videos) on the fly without exhausting PHP memory limits.
- **Eloquent Casts:** Drop-in `TenantEncrypted` casts that automatically resolve the correct key based on the model's relationship.
- **Provider Interface:** You control how tenant keys are fetched and stored (Database, HashiCorp Vault, AWS KMS, etc.).

## Installation

You can install the package via composer:

```bash
composer require illuma-law/laravel-vault-cipher
```

Publish the config file:

```bash
php artisan vendor:publish --tag="vault-cipher-config"
```

## Configuration

The configuration file allows you to define:

- `chunk_size`: The chunk size in bytes for streaming encryption (default: 64KB).
- `default_disk`: The default filesystem disk for encrypted files (default: `local`).
- `key_provider`: The class responsible for resolving tenant keys.

### The Key Provider

You must create a class that implements the `TenantKeyProvider` interface to tell the package how to resolve an encryption key for a given tenant ID:

```php
namespace App\Services;

use IllumaLaw\VaultCipher\Contracts\TenantKeyProvider;
use App\Models\Tenant;

class MyTenantKeyProvider implements TenantKeyProvider
{
    public function getKey(int|string $tenantId): string
    {
        // Example: Retrieve the key from the database and decrypt it using the master APP_KEY
        $tenant = Tenant::findOrFail($tenantId);
        return decrypt($tenant->encrypted_data_key);
    }
}
```

Then, register your provider in `config/vault-cipher.php`:

```php
    'key_provider' => \App\Services\MyTenantKeyProvider::class,
```

The package provides a `VaultKeyGenerator` to create compatible encryption keys:

```php
use IllumaLaw\VaultCipher\Support\VaultKeyGenerator;

// Returns a base64-encoded 32-byte key (AES-256 compatible)
$team->encryption_key = VaultKeyGenerator::generate();
```

## Usage & Integration

### String Encryption

You can encrypt simple strings dynamically using the Facade:

```php
use IllumaLaw\VaultCipher\Facades\TenantEncryptionManager;

$tenantId = 123;
$encrypted = TenantEncryptionManager::encryptString($tenantId, 'highly sensitive medical data');

$decrypted = TenantEncryptionManager::decryptString($tenantId, $encrypted);
```

### File Streaming Encryption

Storing large files securely requires streaming. Vault Cipher chunk-encrypts the file in memory while writing it to disk.

```php
use IllumaLaw\VaultCipher\Facades\TenantEncryptionManager;

$tenantId = 123;

// Stream content directly to an encrypted file on your configured disk
TenantEncryptionManager::store($tenantId, 'contracts/agreement-1.pdf', $binaryContent);

// Retrieve and stream the decrypted content back (e.g., for downloading)
$content = TenantEncryptionManager::get($tenantId, 'contracts/agreement-1.pdf');
```

For streaming operations that require temporary access to decrypted files:

```php
use IllumaLaw\VaultCipher\Facades\TenantEncryptionManager;

// Decrypts to a temp path, executes callback, then cleans up the temp file
TenantEncryptionManager::usingDecryptedTempPath(
    tenantId: $tenantId,
    path: 'contracts/agreement-1.pdf',
    callback: function (string $tempPath) {
        // Process the decrypted file (e.g., pass to OCR, thumbnail generation, etc.)
        return processFile($tempPath);
    },
    disk: 's3'
);
```

You can also encrypt existing files already present on the disk in place:

```php
TenantEncryptionManager::encryptExistingPath($tenantId, 'existing/unencrypted_file.txt');
```

### Eloquent Casts

For database columns, use the provided Eloquent casts to automate encryption and decryption when saving or retrieving models.

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use IllumaLaw\VaultCipher\Casts\TenantEncrypted;
use IllumaLaw\VaultCipher\Casts\TenantEncryptedArray;
use IllumaLaw\VaultCipher\Contracts\TenantCipherable;

class Document extends Model implements TenantCipherable
{
    protected $casts = [
        'content' => TenantEncrypted::class,
        'metadata' => TenantEncryptedArray::class, // Handles arrays/JSON
    ];

    /**
     * Required by TenantCipherable so the Cast knows which key to use.
     */
    public function getTenantIdForCipher(): int|string|null
    {
        // Return the foreign key or relation ID that identifies the tenant
        return $this->team_id; 
    }
}
```

If your model doesn't implement `TenantCipherable`, the cast will attempt to look for a `team_id` or `tenant_id` property automatically as a fallback.

### Key Rotation

When rotating tenant encryption keys, you need to re-encrypt existing data. The package provides `TenantKeyRotator` for this purpose:

```php
use IllumaLaw\VaultCipher\Facades\TenantEncryptionManager;
use IllumaLaw\VaultCipher\Support\TenantKeyRotator;

// Build encrypters from raw keys during rotation
$oldKey = base64_decode($team->encryption_key);
$newKey = base64_decode(VaultKeyGenerator::generate());

$rotator = new TenantKeyRotator(
    oldEncrypter: TenantEncryptionManager::encrypterForRawKey($oldKey),
    newEncrypter: TenantEncryptionManager::encrypterForRawKey($newKey),
    oldFileEncrypter: TenantEncryptionManager::fileEncrypterForRawKey($oldKey),
    newFileEncrypter: TenantEncryptionManager::fileEncrypterForRawKey($newKey),
);

// Re-encrypt a string value (handles passthrough if decryption fails)
$reEncrypted = $rotator->rotateString($ciphertext);

// Re-encrypt a file on disk (detects chunk vs string encryption)
$rotator->rotateFileOnDisk($disk, 'contracts/agreement-1.pdf');
```

## Testing

Run the test suite:

```bash
composer test
```

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
