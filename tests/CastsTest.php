<?php

declare(strict_types=1);

namespace IllumaLaw\VaultCipher\Tests;

use IllumaLaw\VaultCipher\Casts\TenantEncrypted;
use IllumaLaw\VaultCipher\Casts\TenantEncryptedArray;
use IllumaLaw\VaultCipher\Contracts\TenantCipherable;
use IllumaLaw\VaultCipher\Contracts\TenantKeyProvider;
use IllumaLaw\VaultCipher\Facades\TenantEncryptionManager;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

beforeEach(function () {
    $keyProvider = new class implements TenantKeyProvider
    {
        public function getKey(int|string $tenantId): string
        {
            return str_pad((string) $tenantId, 32, '0');
        }
    };

    $this->app->instance(TenantKeyProvider::class, $keyProvider);
});

it('tenant encrypted cast encrypts and decrypts', function () {
    $model = new TestModel;
    $cast = new TenantEncrypted;

    $plaintext = 'top secret';
    $encrypted = $cast->set($model, 'secret', $plaintext, []);

    expect($encrypted)->not->toBe($plaintext)
        ->and(TenantEncryptionManager::isEncryptedPayload(1, $encrypted))->toBeTrue();

    $decrypted = $cast->get($model, 'secret', $encrypted, []);
    expect($decrypted)->toBe($plaintext);
});

it('tenant encrypted array cast encrypts and decrypts arrays', function () {
    $model = new TestModel;
    $cast = new TenantEncryptedArray;

    $data = ['foo' => 'bar', 'nested' => ['a' => 1]];
    $encrypted = $cast->set($model, 'data', $data, []);

    expect($encrypted)->toBeString()
        ->and(TenantEncryptionManager::isEncryptedPayload(1, $encrypted))->toBeTrue();

    $decrypted = $cast->get($model, 'data', $encrypted, []);
    expect($decrypted)->toBe($data);
});

it('throws exception if model does not implement tenant cipherable and has no team_id tenant_id', function () {
    $model = new class extends Model {};
    $cast = new TenantEncrypted;

    $cast->set($model, 'secret', 'test', []);
})->throws(RuntimeException::class, 'must implement [IllumaLaw\VaultCipher\Contracts\TenantCipherable]');

it('handles fallback team_id and tenant_id properties', function () {
    $modelWithTeamId = new class extends Model
    {
        public $team_id = 123;
    };

    $modelWithTenantId = new class extends Model
    {
        public $tenant_id = 'abc';
    };

    $cast = new TenantEncrypted;

    $enc1 = $cast->set($modelWithTeamId, 'secret', 'test', []);
    expect(TenantEncryptionManager::decryptString(123, $enc1))->toBe('test');

    $enc2 = $cast->set($modelWithTenantId, 'secret', 'test', []);
    expect(TenantEncryptionManager::decryptString('abc', $enc2))->toBe('test');
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
    expect(TenantEncryptionManager::decryptString(1, $encrypted))->toBe('123');
});

it('returns plain value if no tenant id can be resolved (though it should throw)', function () {
    // This is hard to trigger because resolveTenantId throws.
})->skip();

class TestModel extends Model implements TenantCipherable
{
    public $team_id = 1;

    public function getTenantIdForCipher(): int|string|null
    {
        return $this->team_id;
    }

    protected $casts = [
        'secret' => TenantEncrypted::class,
        'data'   => TenantEncryptedArray::class,
    ];
}
