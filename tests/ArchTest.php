<?php

declare(strict_types=1);

test('globals')
    ->expect(['dd', 'dump', 'ray', 'var_dump'])
    ->not->toBeUsed();

test('contracts')
    ->expect('IllumaLaw\VaultCipher\Contracts')
    ->toBeInterfaces();

test('facades')
    ->expect('IllumaLaw\VaultCipher\Facades')
    ->toBeClasses()
    ->toExtend('Illuminate\Support\Facades\Facade');

test('strict types')
    ->expect('IllumaLaw\VaultCipher')
    ->toUseStrictTypes();
