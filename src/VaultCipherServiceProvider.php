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
        $this->app->singleton(TenantEncryptionManager::class, function (\Illuminate\Foundation\Application $app): TenantEncryptionManager {
            $keyProvider = $app->make(TenantKeyProvider::class);

            /** @var \Illuminate\Filesystem\FilesystemManager $filesystem */
            $filesystem = $app->make('filesystem');

            return new TenantEncryptionManager(
                $keyProvider,
                $filesystem,
                (int) config('vault-cipher.chunk_size', 65536)
            );
        });

        $provider = config('vault-cipher.key_provider');

        if ($provider) {
            $this->app->bind(TenantKeyProvider::class, $provider);
        }
    }
}
