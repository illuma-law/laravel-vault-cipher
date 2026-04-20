<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Default Encryption Chunk Size
    |--------------------------------------------------------------------------
    |
    | The chunk size (in bytes) to use for streaming encryption and decryption.
    | Larger values may improve performance but will use more memory.
    |
    */
    'chunk_size' => (int) env('VAULT_CIPHER_CHUNK_SIZE', 65536),

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | The default disk to use when storing or retrieving encrypted files
    | if no disk is explicitly provided to the manager.
    |
    */
    'default_disk' => env('VAULT_CIPHER_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Tenant Key Provider
    |--------------------------------------------------------------------------
    |
    | This class is responsible for resolving the encryption key for a given
    | tenant ID. It must implement IllumaLaw\VaultCipher\Contracts\TenantKeyProvider.
    |
    */
    'key_provider' => null,
];
