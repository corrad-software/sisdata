<?php

namespace App\Modules\Dms\Services\Storage;

use App\Modules\Dms\Services\Crypto\EnvelopeEncryptor;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * The only code that touches the `dokumen` disk. Everything written is
 * encrypted first (SRS-DMS-20); everything read is decrypted and verified.
 *
 *   dokumen/<yyyy>/<mm>/<dokumen-id>/v<n>-<rand>.enc   active versions
 *   arkib/...                                          archived (SRS-DMS-22)
 *   kuarantin/<yyyy>/<mm>/<rand>.enc                   failed antivirus (SRS-DMS-07)
 *
 * In production `arkib/` maps to an S3 lifecycle rule (cheaper storage
 * class / separate archive bucket); `kuarantin/` should be on a bucket
 * policy no application role but DMS can read.
 */
class DocumentVault
{
    public function __construct(private EnvelopeEncryptor $encryptor, private string $diskName) {}

    public function disk(): Filesystem
    {
        return Storage::disk($this->diskName);
    }

    /**
     * Encrypt a local plaintext file and store it at $key.
     *
     * @return array{wrapped_key: string, key_id: string, sha256: string, size: int}
     */
    public function put(string $key, string $plainPath): array
    {
        $source = fopen($plainPath, 'rb');
        $tmp = tmpfile();
        if ($source === false || $tmp === false) {
            throw new RuntimeException('Cannot open file for encryption.');
        }

        try {
            $meta = $this->encryptor->encryptStream($source, $tmp);
            rewind($tmp);
            $this->disk()->writeStream($key, $tmp);
        } finally {
            fclose($source);
            fclose($tmp);
        }

        return $meta;
    }

    /**
     * Decrypt the object at $key into a local temp file and return its path.
     * Caller deletes it.
     */
    public function toTempFile(string $key, string $wrappedKey): string
    {
        $path = tempnam(sys_get_temp_dir(), 'dms');
        $out = fopen($path, 'wb');
        if ($out === false) {
            throw new RuntimeException('Cannot create temp file.');
        }
        try {
            $this->decryptTo($key, $wrappedKey, $out);
        } finally {
            fclose($out);
        }

        return $path;
    }

    /**
     * @param  resource  $target
     */
    public function decryptTo(string $key, string $wrappedKey, $target): void
    {
        $in = $this->disk()->readStream($key);
        if (! is_resource($in)) {
            throw new RuntimeException('Stored object not found.');
        }
        try {
            $this->encryptor->decryptStream($in, $target, $wrappedKey);
        } finally {
            fclose($in);
        }
    }

    public function move(string $from, string $to): void
    {
        $this->disk()->move($from, $to);
    }

    public function delete(string $key): void
    {
        $this->disk()->delete($key);
    }

    public function exists(string $key): bool
    {
        return $this->disk()->exists($key);
    }
}
