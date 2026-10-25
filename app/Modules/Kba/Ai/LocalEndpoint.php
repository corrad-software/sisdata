<?php

namespace App\Modules\Kba\Ai;

use InvalidArgumentException;

/**
 * SRS-KBA-15 / SRS-DSH-18: AI endpoints must be inside MAIS's own network.
 * Same rule as MOD-DSH's Ollama adapter: loopback, *.local / *.internal
 * names, or a private / reserved IP. Anything else is refused outright —
 * prompts and document content never leave the environment.
 */
final class LocalEndpoint
{
    /** @throws InvalidArgumentException */
    public static function assert(string $url): void
    {
        $host = parse_url($url, PHP_URL_HOST);
        $scheme = parse_url($url, PHP_URL_SCHEME);
        if (! is_string($host) || ! in_array($scheme, ['http', 'https'], true)) {
            throw new InvalidArgumentException(__('kba::kba.ai.url_tidak_sah'));
        }
        $host = trim(strtolower($host), '[]');
        if ($host === 'localhost' || str_ends_with($host, '.local') || str_ends_with($host, '.internal')) {
            return;
        }
        if (filter_var($host, FILTER_VALIDATE_IP) !== false
            && filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return; // private / loopback / reserved range
        }

        throw new InvalidArgumentException(__('kba::kba.ai.bukan_tempatan'));
    }

    public static function isLocal(string $url): bool
    {
        try {
            self::assert($url);

            return true;
        } catch (InvalidArgumentException) {
            return false;
        }
    }
}
