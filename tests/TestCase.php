<?php

declare(strict_types=1);

namespace IllumaLaw\VaultCipher\Tests;

use IllumaLaw\VaultCipher\VaultCipherServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            VaultCipherServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app): void
    {
        config()->set('database.default', 'testing');
    }
}
