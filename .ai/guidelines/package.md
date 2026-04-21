---
description: Tenant-aware streaming encryption for Laravel — per-tenant keys, file streaming, Eloquent casts
---

# laravel-vault-cipher

Tenant-aware streaming encryption. Each tenant's data is encrypted with their own unique key, eliminating the shared-key blast radius of a single `APP_KEY`.

## Namespace

`IllumaLaw\VaultCipher`

## Key Classes

- `TenantEncryptionManager` — injectable; main service for encrypt/decrypt
- `TenantKeyProvider` contract — implement to supply tenant keys
- `TenantEncrypted` Eloquent cast — auto-resolves key from model relationship

## Config

Publish: `php artisan vendor:publish --tag="vault-cipher-config"`

```php
// config/vault-cipher.php
return [
    'chunk_size'   => 65536,  // bytes for streaming (64KB default)
    'default_disk' => 'local',
    'key_provider' => \App\Services\MyTenantKeyProvider::class,
];
```

## Implementing TenantKeyProvider

```php
use IllumaLaw\VaultCipher\Contracts\TenantKeyProvider;

class MyTenantKeyProvider implements TenantKeyProvider
{
    public function getKey(int|string $tenantId): string
    {
        return Team::find($tenantId)?->encryption_key
            ?? throw new RuntimeException("No key for tenant {$tenantId}");
    }
}
```

## File Streaming Encryption

```php
use IllumaLaw\VaultCipher\TenantEncryptionManager;

$manager = app(TenantEncryptionManager::class);

// Encrypt a file to disk
$manager->encryptFileToDisk(
    tenantId: $team->id,
    sourcePath: '/tmp/upload.pdf',
    disk: 'local',
    destinationPath: 'evidence/file.enc',
);

// Decrypt to a temp path (returns path string)
$tempPath = $manager->decryptToTempPath(
    tenantId: $team->id,
    disk: 'local',
    sourcePath: 'evidence/file.enc',
);
```

## Eloquent Cast

```php
use IllumaLaw\VaultCipher\Casts\TenantEncrypted;

class EvidenceFile extends Model
{
    protected $casts = [
        'sensitive_notes' => TenantEncrypted::class,
    ];
    // Requires $this->team_id to resolve the tenant key
}
```
