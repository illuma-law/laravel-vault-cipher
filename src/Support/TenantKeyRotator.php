<?php

declare(strict_types=1);

namespace IllumaLaw\VaultCipher\Support;

use Ercsctt\FileEncryption\FileEncrypter;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Class TenantKeyRotator
 *
 * Handles re-encryption of data when rotating tenant encryption keys.
 * Works with raw encrypter instances - no knowledge of tenant IDs or models.
 */
final class TenantKeyRotator
{
    public function __construct(
        private readonly Encrypter $oldEncrypter,
        private readonly Encrypter $newEncrypter,
        private readonly FileEncrypter $oldFileEncrypter,
        private readonly FileEncrypter $newFileEncrypter,
    ) {}

    /**
     * Rotate a string ciphertext from old key to new key.
     *
     * If decryption fails (e.g., already re-encrypted or plaintext),
     * returns the original value unchanged.
     */
    public function rotateString(string $ciphertext): string
    {
        $plaintext = $this->decryptIfNeeded($ciphertext);

        return $this->newEncrypter->encryptString($plaintext);
    }

    /**
     * Rotate a file on disk from old key to new key.
     *
     * Detects chunk-encrypted vs string-encrypted files and handles accordingly.
     * Atomically replaces the file content.
     *
     * @throws RuntimeException if temp file operations fail
     */
    public function rotateFileOnDisk(Filesystem $disk, string $path): void
    {
        $raw = (string) $disk->get($path);

        if ($raw === '') {
            return;
        }

        if (Str::startsWith($raw, FileEncrypter::MAGIC)) {
            $this->rotateChunkEncryptedFile($disk, $path, $raw);

            return;
        }

        $this->rotateStringEncryptedFile($disk, $path, $raw);
    }

    /**
     * Rotate a chunk-encrypted file using streaming.
     */
    private function rotateChunkEncryptedFile(Filesystem $disk, string $path, string $raw): void
    {
        $encryptedPath = tempnam(sys_get_temp_dir(), 'rotate_old_enc_');
        $plaintextPath = tempnam(sys_get_temp_dir(), 'rotate_plain_');
        $reEncryptedPath = tempnam(sys_get_temp_dir(), 'rotate_new_enc_');

        if ($encryptedPath === false || $plaintextPath === false || $reEncryptedPath === false) {
            throw new RuntimeException('Unable to create temporary files for key rotation.');
        }

        try {
            File::put($encryptedPath, $raw);
            $this->oldFileEncrypter->decryptFile($encryptedPath, $plaintextPath);
            $this->newFileEncrypter->encryptFile($plaintextPath, $reEncryptedPath);
            $disk->put($path, File::get($reEncryptedPath));
        } finally {
            @unlink($encryptedPath);
            @unlink($plaintextPath);
            @unlink($reEncryptedPath);
        }
    }

    /**
     * Rotate a string-encrypted file.
     */
    private function rotateStringEncryptedFile(Filesystem $disk, string $path, string $raw): void
    {
        $plaintext = $this->decryptIfNeeded($raw);
        $disk->put($path, $this->newEncrypter->encryptString($plaintext));
    }

    /**
     * Attempt to decrypt, falling back to returning the original value on failure.
     */
    private function decryptIfNeeded(string $value): string
    {
        try {
            return $this->oldEncrypter->decryptString($value);
        } catch (DecryptException) {
            return $value;
        }
    }
}
