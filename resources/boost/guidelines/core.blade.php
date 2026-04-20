# illuma-law/laravel-vault-cipher

Tenant-aware streaming encryption for strings and files.

## Usage

### String Encryption

```php
use IllumaLaw\VaultCipher\Facades\TenantEncryptionManager;

$encrypted = TenantEncryptionManager::encryptString($tenantId, 'sensitive data');
$decrypted = TenantEncryptionManager::decryptString($tenantId, $encrypted);
```

### File Encryption (Streaming)

```php
// Store and encrypt
TenantEncryptionManager::store($tenantId, 'path/file.txt', $content);

// Retrieve and decrypt
$content = TenantEncryptionManager::get($tenantId, 'path/file.txt');
```

### Eloquent Casts
Implement `TenantCipherable` on models.

```php
use IllumaLaw\VaultCipher\Casts\TenantEncrypted;

class Document extends Model implements TenantCipherable {
    protected $casts = ['content' => TenantEncrypted::class];
    public function getTenantIdForCipher(): int|string|null { return $this->team_id; }
}
```

## Configuration

Publish config: `php artisan vendor:publish --tag="vault-cipher-config"`

Key options in `config/vault-cipher.php`:
- `chunk_size`: Default 64KB.
- `key_provider`: Class implementing `TenantKeyProvider`.

### Key Provider Example

```php
class MyTenantKeyProvider implements TenantKeyProvider {
    public function getKey($tenantId): string {
        return decrypt(Tenant::find($tenantId)->encryption_key);
    }
}
```
