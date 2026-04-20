<?php

declare(strict_types=1);

namespace IllumaLaw\VaultCipher;

use Ercsctt\FileEncryption\FileEncrypter;
use IllumaLaw\VaultCipher\Contracts\TenantKeyProvider;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Class TenantEncryptionManager
 *
 * Manages tenant-aware streaming encryption and decryption.
 */
class TenantEncryptionManager
{
    /**
     * @var array<int|string, Encrypter>
     */
    protected array $encrypters = [];

    public function __construct(
        protected readonly TenantKeyProvider $keyProvider,
        protected readonly FilesystemFactory $filesystem,
        protected readonly int $chunkSize = 65536,
    ) {}

    public function forgetTenant(int|string $tenantId): void
    {
        unset($this->encrypters[$tenantId]);
    }

    public function encrypterForTenant(int|string $tenantId): Encrypter
    {
        if (isset($this->encrypters[$tenantId])) {
            return $this->encrypters[$tenantId];
        }

        $key = $this->keyProvider->getKey($tenantId);

        return $this->encrypters[$tenantId] = new Encrypter($key, 'AES-256-CBC');
    }

    public function encryptString(int|string $tenantId, string $value): string
    {
        return $this->encrypterForTenant($tenantId)->encryptString($value);
    }

    public function decryptString(int|string $tenantId, string $value): string
    {
        return $this->encrypterForTenant($tenantId)->decryptString($value);
    }

    public function isEncryptedPayload(int|string $tenantId, string $value): bool
    {
        try {
            $this->decryptString($tenantId, $value);

            return true;
        } catch (DecryptException) {
            return false;
        }
    }

    public function store(int|string $tenantId, string $path, string $contents, ?string $disk = null): void
    {
        $sourcePath = $this->createTempFile('vault_encrypt_source_');
        $encryptedPath = $this->createTempFile('vault_encrypt_target_');

        try {
            File::put($sourcePath, $contents);

            $this->fileEncrypterForTenant($tenantId)->encryptFile($sourcePath, $encryptedPath);
            $this->putTempFileToDisk($encryptedPath, $path, $disk);
        } finally {
            $this->deleteTempFile($sourcePath);
            $this->deleteTempFile($encryptedPath);
        }
    }

    public function get(int|string $tenantId, string $path, ?string $disk = null): string
    {
        $contents = (string) $this->disk($disk)->get($path);

        if ($this->isChunkEncryptedPayload($contents)) {
            $encryptedPath = $this->createTempFile('vault_encrypted_');
            $decryptedContents = '';

            try {
                File::put($encryptedPath, $contents);

                $this->fileEncrypterForTenant($tenantId)->decryptedStream($encryptedPath, function (string $chunk) use (&$decryptedContents): void {
                    $decryptedContents .= $chunk;
                });

                return $decryptedContents;
            } finally {
                $this->deleteTempFile($encryptedPath);
            }
        }

        if (! $this->isEncryptedPayload($tenantId, $contents)) {
            return $contents;
        }

        return $this->decryptString($tenantId, $contents);
    }

    public function encryptExistingPath(int|string $tenantId, string $path, ?string $disk = null): bool
    {
        if (! $this->disk($disk)->exists($path)) {
            return false;
        }

        $sourcePath = $this->copyDiskFileToTempPath($path, 'vault_encrypt_existing_source_', $disk);
        $encryptedPath = $this->createTempFile('vault_encrypt_existing_target_');

        try {
            if ($this->isChunkEncryptedFile($sourcePath)) {
                return false;
            }

            $sourceContents = File::get($sourcePath);

            if (! is_string($sourceContents) || $sourceContents === '') {
                return false;
            }

            if ($this->isEncryptedPayload($tenantId, $sourceContents)) {
                return false;
            }

            $this->fileEncrypterForTenant($tenantId)->encryptFile($sourcePath, $encryptedPath);
            $this->putTempFileToDisk($encryptedPath, $path, $disk);

            return true;
        } finally {
            $this->deleteTempFile($sourcePath);
            $this->deleteTempFile($encryptedPath);
        }
    }

    public function decryptToTempPath(int|string $tenantId, string $path, ?string $disk = null): string
    {
        $tempPath = tempnam(sys_get_temp_dir(), 'vault_decrypted_');

        if ($tempPath === false) {
            throw new RuntimeException('Unable to create temporary decrypted file.');
        }

        $encryptedPath = $this->copyDiskFileToTempPath($path, 'vault_encrypted_', $disk);

        try {
            if ($this->isChunkEncryptedFile($encryptedPath)) {
                $outputHandle = fopen($tempPath, 'wb');

                if ($outputHandle === false) {
                    throw new RuntimeException('Unable to open temporary decrypted file for writing.');
                }

                try {
                    $this->fileEncrypterForTenant($tenantId)->decryptedStream($encryptedPath, function (string $chunk) use ($outputHandle): void {
                        fwrite($outputHandle, $chunk);
                    });
                } finally {
                    fclose($outputHandle);
                }

                return $tempPath;
            }

            File::put($tempPath, $this->get($tenantId, $path, $disk));
        } finally {
            $this->deleteTempFile($encryptedPath);
        }

        return $tempPath;
    }

    public function fileEncrypterForTenant(int|string $tenantId): FileEncrypter
    {
        return new FileEncrypter(
            key: $this->keyProvider->getKey($tenantId),
            chunkSize: $this->chunkSize,
        );
    }

    public function isChunkEncryptedPayload(string $contents): bool
    {
        return Str::startsWith($contents, FileEncrypter::MAGIC);
    }

    public function isChunkEncryptedFile(string $path): bool
    {
        $handle = @fopen($path, 'rb');

        if ($handle === false) {
            return false;
        }

        try {
            $magic = fread($handle, strlen(FileEncrypter::MAGIC));
        } finally {
            fclose($handle);
        }

        return $magic === FileEncrypter::MAGIC;
    }

    protected function disk(?string $disk = null): Filesystem
    {
        return $this->filesystem->disk($disk ?? config('vault-cipher.default_disk', 'local'));
    }

    protected function createTempFile(string $prefix): string
    {
        $tempPath = tempnam(sys_get_temp_dir(), $prefix);

        if ($tempPath === false) {
            throw new RuntimeException('Unable to create temporary file.');
        }

        return $tempPath;
    }

    protected function copyDiskFileToTempPath(string $path, string $prefix, ?string $disk = null): string
    {
        $stream = $this->disk($disk)->readStream($path);

        if (! is_resource($stream)) {
            throw new RuntimeException("Unable to read file [{$path}] from disk.");
        }

        $tempPath = $this->createTempFile($prefix);
        $tempHandle = fopen($tempPath, 'wb');

        if ($tempHandle === false) {
            fclose($stream);
            throw new RuntimeException('Unable to open temporary file for writing.');
        }

        try {
            stream_copy_to_stream($stream, $tempHandle);
        } finally {
            fclose($stream);
            fclose($tempHandle);
        }

        return $tempPath;
    }

    protected function putTempFileToDisk(string $tempPath, string $path, ?string $disk = null): void
    {
        $stream = fopen($tempPath, 'rb');

        if (! is_resource($stream)) {
            throw new RuntimeException('Unable to open temporary file for upload.');
        }

        try {
            $this->disk($disk)->put($path, $stream);
        } finally {
            fclose($stream);
        }
    }

    protected function deleteTempFile(string $path): void
    {
        if (is_file($path)) {
            @unlink($path);
        }
    }
}
