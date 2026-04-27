<?php

declare(strict_types=1);

namespace IllumaLaw\VaultCipher\Tests;

use IllumaLaw\VaultCipher\Contracts\TenantKeyProvider;
use IllumaLaw\VaultCipher\Facades\TenantEncryptionManager;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->keyProvider = new class implements TenantKeyProvider
    {
        public function getKey(int|string $tenantId): string
        {
            return str_pad((string) $tenantId, 32, '0');
        }
    };

    /** @var Application $app */
    $app = $this->app;
    $app->instance(TenantKeyProvider::class, $this->keyProvider);
});

it('encrypts and decrypts strings per tenant', function () {
    $tenantId = 1;
    $plaintext = 'hello world';

    $encrypted = TenantEncryptionManager::encryptString($tenantId, $plaintext);

    expect($encrypted)->not->toBe($plaintext)
        ->and(TenantEncryptionManager::decryptString($tenantId, $encrypted))->toBe($plaintext);
});

it('uses different keys for different tenants', function () {
    $tenant1 = 1;
    $tenant2 = 2;
    $plaintext = 'secret';

    $encrypted1 = TenantEncryptionManager::encryptString($tenant1, $plaintext);
    $encrypted2 = TenantEncryptionManager::encryptString($tenant2, $plaintext);

    expect($encrypted1)->not->toBe($encrypted2);

    TenantEncryptionManager::decryptString($tenant2, $encrypted1);
})->throws(DecryptException::class);

it('stores and retrieves encrypted files', function () {
    Storage::fake('local');
    $tenantId = 1;
    $path = 'test.txt';
    $contents = 'file contents';

    TenantEncryptionManager::store($tenantId, $path, $contents, 'local');

    $diskContents = Storage::disk('local')->get($path);
    expect($diskContents)->not->toBe($contents)
        ->and($diskContents)->toStartWith('LENC');

    $retrieved = TenantEncryptionManager::get($tenantId, $path, 'local');
    expect($retrieved)->toBe($contents);
});

it('can encrypt an existing path', function () {
    Storage::fake('local');
    $tenantId = 1;
    $path = 'existing.txt';
    $contents = 'existing contents';

    Storage::disk('local')->put($path, $contents);

    $result = TenantEncryptionManager::encryptExistingPath($tenantId, $path, 'local');

    expect($result)->toBeTrue();

    $diskContents = Storage::disk('local')->get($path);
    expect($diskContents)->not->toBe($contents)
        ->and($diskContents)->toStartWith('LENC');

    expect(TenantEncryptionManager::get($tenantId, $path, 'local'))->toBe($contents);
});

it('can decrypt to a temporary path', function () {
    Storage::fake('local');
    $tenantId = 1;
    $path = 'to_decrypt.txt';
    $contents = 'some data';

    TenantEncryptionManager::store($tenantId, $path, $contents, 'local');

    $tempPath = TenantEncryptionManager::decryptToTempPath($tenantId, $path, 'local');

    expect($tempPath)->toBeFile()
        ->and(file_get_contents($tempPath))->toBe($contents);

    @unlink($tempPath);
});

it('detects encrypted payloads', function () {
    $tenantId = 1;
    $encrypted = TenantEncryptionManager::encryptString($tenantId, 'test');

    expect(TenantEncryptionManager::isEncryptedPayload($tenantId, $encrypted))->toBeTrue()
        ->and(TenantEncryptionManager::isEncryptedPayload($tenantId, 'not encrypted'))->toBeFalse();
});

it('forgets tenant encrypters', function () {
    $tenantId = 1;
    TenantEncryptionManager::encryptString($tenantId, 'test');

    $manager = app(\IllumaLaw\VaultCipher\TenantEncryptionManager::class);
    $reflection = new \ReflectionClass($manager);
    $property = $reflection->getProperty('encrypters');
    $property->setAccessible(true);

    expect($property->getValue($manager))->toHaveKey($tenantId);

    TenantEncryptionManager::forgetTenant($tenantId);

    expect($property->getValue($manager))->not->toHaveKey($tenantId);
});

it('returns content as is if not encrypted in get()', function () {
    Storage::fake('local');
    $path = 'plain.txt';
    Storage::disk('local')->put($path, 'plain text');

    expect(TenantEncryptionManager::get(1, $path, 'local'))->toBe('plain text');
});

it('returns false when encrypting non-existent path', function () {
    Storage::fake('local');
    expect(TenantEncryptionManager::encryptExistingPath(1, 'missing.txt', 'local'))->toBeFalse();
});

it('returns false when encrypting already encrypted path', function () {
    Storage::fake('local');
    $tenantId = 1;
    $path = 'already.txt';
    TenantEncryptionManager::store($tenantId, $path, 'content', 'local');

    expect(TenantEncryptionManager::encryptExistingPath($tenantId, $path, 'local'))->toBeFalse();
});

it('returns false when encrypting empty file', function () {
    Storage::fake('local');
    $path = 'empty.txt';
    Storage::disk('local')->put($path, '');

    expect(TenantEncryptionManager::encryptExistingPath(1, $path, 'local'))->toBeFalse();
});

it('throws exception if unable to read stream from disk', function () {
    Storage::fake('local');
})->skip('Hard to mock readStream failure with Storage::fake');
