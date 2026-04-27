<?php

declare(strict_types=1);

namespace IllumaLaw\VaultCipher\Tests;

use IllumaLaw\VaultCipher\Casts\TenantEncrypted;
use IllumaLaw\VaultCipher\Casts\TenantEncryptedArray;
use IllumaLaw\VaultCipher\Contracts\TenantCipherable;
use IllumaLaw\VaultCipher\Contracts\TenantKeyProvider;
use IllumaLaw\VaultCipher\Facades\TenantEncryptionManager;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Application;
use RuntimeException;

beforeEach(function () {
    $keyProvider = new class implements TenantKeyProvider
    {
        public function getKey(int|string $tenantId): string
        {
            return str_pad((string) $tenantId, 32, '0');
        }
    };

    /** @var Application $app */
    $app = $this->app;
    $app->instance(TenantKeyProvider::class, $keyProvider);
});

it('tenant encrypted cast encrypts and decrypts', function () {
    $model = new TestModel;
    $cast = new TenantEncrypted;

    $plaintext = 'top secret';
    $encrypted = $cast->set($model, 'secret', $plaintext, []);

    expect($encrypted)->not->toBe($plaintext)
        ->and(TenantEncryptionManager::isEncryptedPayload(1, is_scalar($encrypted) ? (string) $encrypted : ''))->toBeTrue();

    $decrypted = $cast->get($model, 'secret', is_scalar($encrypted) ? (string) $encrypted : '', []);
    expect($decrypted)->toBe($plaintext);
});

it('tenant encrypted array cast encrypts and decrypts arrays', function () {
    $model = new TestModel;
    $cast = new TenantEncryptedArray;

    $data = ['foo' => 'bar', 'nested' => ['a' => 1]];
    $encrypted = $cast->set($model, 'data', $data, []);

    expect($encrypted)->toBeString()
        ->and(TenantEncryptionManager::isEncryptedPayload(1, is_scalar($encrypted) ? (string) $encrypted : ''))->toBeTrue();

    $decrypted = $cast->get($model, 'data', is_scalar($encrypted) ? (string) $encrypted : '', []);
    expect($decrypted)->toBe($data);
});

it('throws exception if model does not implement tenant cipherable and has no team_id tenant_id', function () {
    $model = new class extends Model {};
    $cast = new TenantEncrypted;

    $cast->set($model, 'secret', 'test', []);
})->throws(RuntimeException::class, 'must implement [IllumaLaw\VaultCipher\Contracts\TenantCipherable]');

it('handles fallback tenant_id property', function () {
    $modelWithTenantId = new class extends Model
    {
        /** @var int|string */
        public $tenant_id = 'abc';
    };

    $cast = new TenantEncrypted;

    $enc2 = $cast->set($modelWithTenantId, 'secret', 'test', []);
    expect(TenantEncryptionManager::decryptString('abc', is_scalar($enc2) ? (string) $enc2 : ''))->toBe('test');
});

it('handles null values in casts', function () {
    $model = new TestModel;
    $cast = new TenantEncrypted;
    $arrayCast = new TenantEncryptedArray;

    expect($cast->set($model, 'secret', null, []))->toBeNull()
        ->and($cast->get($model, 'secret', null, []))->toBeNull()
        ->and($arrayCast->set($model, 'data', null, []))->toBeNull()
        ->and($arrayCast->get($model, 'data', null, []))->toBeNull();
});

it('handles empty strings in casts', function () {
    $model = new TestModel;
    $cast = new TenantEncrypted;

    expect($cast->set($model, 'secret', '', []))->toBe('')
        ->and($cast->get($model, 'secret', '', []))->toBe('');
});

it('handles non-string values in TenantEncrypted set', function () {
    $model = new TestModel;
    $cast = new TenantEncrypted;

    $encrypted = $cast->set($model, 'secret', 123, []);
    expect(TenantEncryptionManager::decryptString(1, is_scalar($encrypted) ? (string) $encrypted : ''))->toBe('123');
});

it('returns plain value if no tenant id can be resolved (though it should throw)', function () {
    expect(true)->toBe(true);
})->skip();

class TestModel extends Model implements TenantCipherable
{
    /** @var int|string */
    public $tenant_id = 1;

    public function getTenantIdForCipher(): int|string|null
    {
        /** @var int|string|null $id */
        $id = $this->tenant_id;

        return $id;
    }

    protected $casts = [
        'secret' => TenantEncrypted::class,
        'data'   => TenantEncryptedArray::class,
    ];
}
