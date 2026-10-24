<?php

namespace App\Modules\Dms\Services\Crypto;

use RuntimeException;

/**
 * SRS-DMS-20: application-level envelope encryption, AES-256-GCM.
 *
 * Every file gets a random 256-bit data key (DEK). The file is encrypted in
 * 1 MiB chunks (so 250 MB spatial files never sit in memory), each chunk an
 * independent GCM message whose nonce is <8-byte random prefix><4-byte
 * counter> and whose AAD binds the counter and a "final chunk" flag —
 * reordering, truncation or appending is detected. The DEK is wrapped with
 * the key-encryption key (KEK) and stored next to the version row; the KEK
 * never touches storage. Disposal deletes the wrapped DEK (crypto-shredding).
 *
 * Why not MinIO/S3 SSE: SSE-S3 needs MinIO KMS (KES), which a local Homebrew
 * MinIO doesn't run, and SSE alone doesn't protect against a leaked bucket
 * credential. In production the bucket can additionally enable SSE-KMS — it
 * stacks with this layer.
 *
 * File format:  "SDMS" | 0x01 | prefix(8) | { final(1) | len(4, BE) | ciphertext | tag(16) }*
 * (the final flag is authenticated through the AAD, so it can't be flipped).
 */
class EnvelopeEncryptor
{
    public const ALGORITHM = 'AES-256-GCM';

    private const MAGIC = "SDMS\x01";

    private const CHUNK = 1048576;

    private const CIPHER = 'aes-256-gcm';

    private string $kek;

    public function __construct(?string $configuredKey, string $appKey)
    {
        $this->kek = $this->resolveKek($configuredKey, $appKey);
    }

    /** Short fingerprint of the KEK, stored per version so keys can be rotated. */
    public function keyId(): string
    {
        return substr(hash('sha256', 'kek-id'.$this->kek), 0, 16);
    }

    /**
     * Encrypt $source into $target. Returns the wrapped data key and the
     * SHA-256 of the plaintext.
     *
     * @param  resource  $source
     * @param  resource  $target
     * @return array{wrapped_key: string, key_id: string, sha256: string, size: int}
     */
    public function encryptStream($source, $target): array
    {
        $dek = random_bytes(32);
        $prefix = random_bytes(8);
        $hash = hash_init('sha256');
        $size = 0;

        $this->write($target, self::MAGIC.$prefix);

        $counter = 0;
        $current = $this->readChunk($source);
        do {
            $next = $current === '' ? '' : $this->readChunk($source);
            $final = $next === '';

            hash_update($hash, $current);
            $size += strlen($current);

            $tag = '';
            $cipher = openssl_encrypt($current, self::CIPHER, $dek, OPENSSL_RAW_DATA, $this->nonce($prefix, $counter), $tag, $this->aad($counter, $final), 16);
            if ($cipher === false) {
                throw new RuntimeException('Encryption failed.');
            }
            $this->write($target, ($final ? "\x01" : "\x00").pack('N', strlen($cipher)).$cipher.$tag);

            $counter++;
            $current = $next;
        } while (! $final);

        sodium_memzero($prefix);
        $wrapped = $this->wrap($dek);
        sodium_memzero($dek);

        return ['wrapped_key' => $wrapped, 'key_id' => $this->keyId(), 'sha256' => hash_final($hash), 'size' => $size];
    }

    /**
     * Decrypt $source into $target, verifying every chunk.
     *
     * @param  resource  $source
     * @param  resource  $target
     *
     * @throws RuntimeException on a wrong key or tampered/truncated content
     */
    public function decryptStream($source, $target, string $wrappedKey): void
    {
        $dek = $this->unwrap($wrappedKey);

        $header = $this->readExact($source, strlen(self::MAGIC) + 8);
        if (! str_starts_with($header, self::MAGIC)) {
            throw new RuntimeException('Not an encrypted DMS object.');
        }
        $prefix = substr($header, strlen(self::MAGIC));

        $counter = 0;
        $final = false;
        while (! $final) {
            $flag = $this->readExact($source, 1, allowEof: true);
            if ($flag === '') {
                throw new RuntimeException('Encrypted object is truncated.');
            }
            $final = $flag === "\x01";
            $len = (int) unpack('N', $this->readExact($source, 4))[1];
            if ($len > self::CHUNK) {
                throw new RuntimeException('Encrypted object is corrupt.');
            }
            $cipher = $len > 0 ? $this->readExact($source, $len) : '';
            $tag = $this->readExact($source, 16);

            $plain = openssl_decrypt($cipher, self::CIPHER, $dek, OPENSSL_RAW_DATA, $this->nonce($prefix, $counter), $tag, $this->aad($counter, $final));
            if ($plain === false) {
                throw new RuntimeException('Encrypted object failed integrity check.');
            }
            $this->write($target, $plain);
            $counter++;
        }

        sodium_memzero($dek);
    }

    /** Convenience for small payloads (tests, previews). */
    public function encryptString(string $plain): array
    {
        $in = $this->memoryStream($plain);
        $out = fopen('php://temp', 'w+b');
        $meta = $this->encryptStream($in, $out);
        rewind($out);

        return [...$meta, 'ciphertext' => (string) stream_get_contents($out)];
    }

    public function decryptString(string $cipher, string $wrappedKey): string
    {
        $out = fopen('php://temp', 'w+b');
        $this->decryptStream($this->memoryStream($cipher), $out, $wrappedKey);
        rewind($out);

        return (string) stream_get_contents($out);
    }

    private function wrap(string $dek): string
    {
        $nonce = random_bytes(12);
        $tag = '';
        $cipher = openssl_encrypt($dek, self::CIPHER, $this->kek, OPENSSL_RAW_DATA, $nonce, $tag, 'sisdata-dms-dek', 16);
        if ($cipher === false) {
            throw new RuntimeException('Key wrap failed.');
        }

        return 'v1:'.base64_encode($nonce.$tag.$cipher);
    }

    private function unwrap(string $wrapped): string
    {
        if (! str_starts_with($wrapped, 'v1:')) {
            throw new RuntimeException('Unknown wrapped key format.');
        }
        $raw = base64_decode(substr($wrapped, 3), true);
        if ($raw === false || strlen($raw) < 28) {
            throw new RuntimeException('Corrupt wrapped key.');
        }
        $dek = openssl_decrypt(substr($raw, 28), self::CIPHER, $this->kek, OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16), 'sisdata-dms-dek');
        if ($dek === false) {
            throw new RuntimeException('Data key cannot be unwrapped (wrong key-encryption key?).');
        }

        return $dek;
    }

    private function resolveKek(?string $configured, string $appKey): string
    {
        if ($configured !== null && $configured !== '') {
            $key = str_starts_with($configured, 'base64:') ? base64_decode(substr($configured, 7), true) : $configured;
            if (! is_string($key) || strlen($key) !== 32) {
                throw new RuntimeException('DMS_ENCRYPTION_KEY must be 32 bytes (use base64:...).');
            }

            return $key;
        }

        $base = str_starts_with($appKey, 'base64:') ? (string) base64_decode(substr($appKey, 7), true) : $appKey;
        if ($base === '') {
            throw new RuntimeException('No key available for document encryption.');
        }

        return hash_hkdf('sha256', $base, 32, 'sisdata-dms-kek-v1');
    }

    private function nonce(string $prefix, int $counter): string
    {
        return $prefix.pack('N', $counter);
    }

    private function aad(int $counter, bool $final): string
    {
        return 'sdms'.pack('N', $counter).($final ? "\x01" : "\x00");
    }

    /** @param resource $stream */
    private function readChunk($stream): string
    {
        $buffer = '';
        while (strlen($buffer) < self::CHUNK && ! feof($stream)) {
            $part = fread($stream, self::CHUNK - strlen($buffer));
            if ($part === false || $part === '') {
                break;
            }
            $buffer .= $part;
        }

        return $buffer;
    }

    /** @param resource $stream */
    private function readExact($stream, int $length, bool $allowEof = false): string
    {
        $buffer = '';
        while (strlen($buffer) < $length && ! feof($stream)) {
            $part = fread($stream, $length - strlen($buffer));
            if ($part === false || $part === '') {
                break;
            }
            $buffer .= $part;
        }
        if (strlen($buffer) !== $length && ! ($allowEof && $buffer === '')) {
            throw new RuntimeException('Encrypted object is truncated.');
        }

        return $buffer;
    }

    /** @param resource $stream */
    private function write($stream, string $data): void
    {
        if ($data !== '' && fwrite($stream, $data) !== strlen($data)) {
            throw new RuntimeException('Write failed.');
        }
    }

    /** @return resource */
    private function memoryStream(string $data)
    {
        $stream = fopen('php://temp', 'w+b');
        fwrite($stream, $data);
        rewind($stream);

        return $stream;
    }
}
