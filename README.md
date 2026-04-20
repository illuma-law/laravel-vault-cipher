# Laravel Vault Cipher

[![Latest Version on Packagist](https://img.shields.io/packagist/v/illuma-law/laravel-vault-cipher.svg?style=flat-square)](https://packagist.org/packages/illuma-law/laravel-vault-cipher)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/illuma-law/laravel-vault-cipher/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/illuma-law/laravel-vault-cipher/actions/workflows/run-tests.yml)
[![Total Downloads](https://img.shields.io/packagist/dt/illuma-law/laravel-vault-cipher.svg?style=flat-square)](https://packagist.org/packages/illuma-law/laravel-vault-cipher)

Tenant-aware streaming encryption for Laravel. This package allows you to encrypt strings and files using tenant-specific keys, with support for streaming large files to avoid memory exhaustion.

## Installation

You can install the package via composer:

```bash
composer require illuma-law/laravel-vault-cipher
```

You should publish the config file with:

```bash
php artisan vendor:publish --tag="vault-cipher-config"
```

## Configuration

The configuration file allows you to define:
- `chunk_size`: The chunk size for streaming encryption (default: 64KB).
- `default_disk`: The default filesystem disk for encrypted files.
- `key_provider`: The class responsible for providing tenant keys.

### Key Provider

You must implement the `TenantKeyProvider` interface to resolve encryption keys for your tenants:

```php
use IllumaLaw\VaultCipher\Contracts\TenantKeyProvider;

class MyTenantKeyProvider implements TenantKeyProvider
{
    public function getKey(int|string $tenantId): string
    {
        // Retrieve the key from your vault, database, or environment
        return decrypt(Tenant::find($tenantId)->encryption_key);
    }
}
```

Register your provider in `config/vault-cipher.php`.

## Usage

### String Encryption

```php
use IllumaLaw\VaultCipher\Facades\TenantEncryptionManager;

$tenantId = 123;
$encrypted = TenantEncryptionManager::encryptString($tenantId, 'sensitive data');
$decrypted = TenantEncryptionManager::decryptString($tenantId, $encrypted);
```

### File Encryption

Store and retrieve files using streaming encryption:

```php
TenantEncryptionManager::store($tenantId, 'path/to/file.txt', 'Large content...');

$content = TenantEncryptionManager::get($tenantId, 'path/to/file.txt');
```

You can also encrypt existing files on disk:

```php
TenantEncryptionManager::encryptExistingPath($tenantId, 'existing/file.txt');
```

### Eloquent Casts

Automatically encrypt/decrypt model attributes based on the tenant:

```php
use IllumaLaw\VaultCipher\Casts\TenantEncrypted;
use IllumaLaw\VaultCipher\Casts\TenantEncryptedArray;
use IllumaLaw\VaultCipher\Contracts\TenantCipherable;
use Illuminate\Database\Eloquent\Model;

class Document extends Model implements TenantCipherable
{
    protected $casts = [
        'content' => TenantEncrypted::class,
        'metadata' => TenantEncryptedArray::class,
    ];

    public function getTenantIdForCipher(): int|string|null
    {
        return $this->team_id;
    }
}
```

The model must implement `TenantCipherable` or have a `team_id` or `tenant_id` property.

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Security

If you discover any security-related issues, please email security@illuma.law instead of using the issue tracker.

## Credits

- [Menes](https://github.com/menes)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
