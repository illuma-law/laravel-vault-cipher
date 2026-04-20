<?php

declare(strict_types=1);

namespace IllumaLaw\VaultCipher;

use IllumaLaw\VaultCipher\Contracts\TenantKeyProvider;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

/**
 * Class VaultCipherServiceProvider
 *
 * Service provider for the Vault Cipher package.
 */
class VaultCipherServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-vault-cipher')
            ->hasConfigFile('vault-cipher');
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(TenantEncryptionManager::class, function ($app) {
            $keyProvider = $app->make(TenantKeyProvider::class);

            return new TenantEncryptionManager(
                $keyProvider,
                $app->make('filesystem'),
                config('vault-cipher.chunk_size', 65536)
            );
        });

        $provider = config('vault-cipher.key_provider');

        if ($provider) {
            $this->app->bind(TenantKeyProvider::class, $provider);
        }
    }
}
