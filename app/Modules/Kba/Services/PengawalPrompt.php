<?php

namespace App\Modules\Kba\Services;

/**
 * SRS-KBA-19: controls against prompt injection from indexed content.
 *  - segments that carry embedded instructions are flagged at index time;
 *  - before any text reaches the model, instruction-like phrases and our own
 *    context delimiters are neutralised, so a document can neither "speak as
 *    the system" nor break out of its <sumber> block;
 *  - system instructions travel in a separate message (LlmGateway).
 * Retrieval permissions are applied in SQL before this point, so even a
 * successful injection could only see passages the user may already read.
 */
final class PengawalPrompt
{
    public const GANTIAN = '[arahan terbenam dibuang]';

    /** @var list<string> */
    private const CORAK = [
        '/\b(ignore|disregard|forget|override)\b[^.\n]{0,40}\b(previous|prior|above|earlier|all|any|the)\b[^.\n]{0,30}\b(instructions?|prompts?|rules?|directions?)\b/iu',
        '/\b(abaikan|lupakan|langgar|batalkan)\b[^.\n]{0,40}\b(arahan|perintah|peraturan|panduan)\b/iu',
        '/\b(you are now|from now on you|act as|pretend to be|anda kini|anda sekarang|mulai sekarang anda|berlagak sebagai)\b/iu',
        '/\b(system prompt|prompt sistem|developer mode|mod pembangun|jailbreak)\b/iu',
        '/\b(reveal|show|print|dedahkan|tunjukkan|paparkan)\b[^.\n]{0,40}\b(sulit|rahsia|secret|confidential|password|kata laluan|classified|semua dokumen|all documents)\b/iu',
        '/<\/?\s*(system|assistant|user|sumber|instructions?|arahan)\b[^>]*>/iu',
        '/\[\/?(INST|SYS)\]|<<\/?SYS>>|<\|im_(start|end)\|>/iu',
        '/^\s*(system|assistant|sistem|pembantu)\s*:/imu',
    ];

    public static function detect(string $text): bool
    {
        foreach (self::CORAK as $pattern) {
            if (preg_match($pattern, $text) === 1) {
                return true;
            }
        }

        return false;
    }

    public static function neutralise(string $text): string
    {
        foreach (self::CORAK as $pattern) {
            $text = preg_replace($pattern, self::GANTIAN, $text) ?? $text;
        }

        return $text;
    }
}
