<?php

declare(strict_types=1);

namespace IllumaLaw\VaultCipher;

use IllumaLaw\VaultCipher\Contracts\TenantKeyProvider;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Foundation\Application;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

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
        $this->app->singleton(TenantEncryptionManager::class, function (Application $app): TenantEncryptionManager {
            $keyProvider = $app->make(TenantKeyProvider::class);

            /** @var FilesystemManager $filesystem */
            $filesystem = $app->make('filesystem');

            $chunkSize = config('vault-cipher.chunk_size', 65536);

            return new TenantEncryptionManager(
                $keyProvider,
                $filesystem,
                is_numeric($chunkSize) ? (int) $chunkSize : 65536
            );
        });

        $provider = config('vault-cipher.key_provider');

        if (is_string($provider) || $provider instanceof \Closure) {
            $this->app->bind(TenantKeyProvider::class, $provider);
        }
    }
}
