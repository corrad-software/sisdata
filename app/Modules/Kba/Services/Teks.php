<?php

namespace App\Modules\Kba\Services;

/**
 * Small, dependency-free text helpers shared by indexing and retrieval:
 * tokenising, BM/EN stop words, language detection and question
 * normalisation (for "most asked" analytics).
 */
final class Teks
{
    /** Bahasa Melayu + English function words; never count as evidence. */
    private const STOP = [
        // ms
        'apa', 'apakah', 'bagaimana', 'bagaimanakah', 'siapa', 'siapakah', 'bila', 'bilakah', 'mana', 'manakah', 'mengapa', 'kenapa', 'berapa', 'berapakah',
        'adakah', 'ialah', 'adalah', 'yang', 'dan', 'atau', 'di', 'ke', 'dari', 'daripada', 'pada', 'untuk', 'bagi', 'dengan', 'dalam', 'oleh', 'ini', 'itu',
        'saya', 'kami', 'kita', 'anda', 'awak', 'dia', 'mereka', 'boleh', 'akan', 'telah', 'sudah', 'ada', 'tidak', 'tak', 'bukan', 'juga', 'serta', 'sahaja',
        'hendaklah', 'perlu', 'mesti', 'harus', 'sebagai', 'tersebut', 'setiap', 'semua', 'sila', 'tolong', 'terangkan', 'jelaskan', 'beritahu', 'cara', 'kah',
        'lah', 'pun', 'nya', 'para', 'sesuatu', 'satu', 'se', 'secara', 'iaitu', 'jika', 'kalau', 'apabila', 'supaya', 'agar', 'tentang', 'mengenai', 'antara',
        'sistem', 'modul',
        // en
        'what', 'which', 'who', 'whom', 'when', 'where', 'why', 'how', 'is', 'are', 'was', 'were', 'be', 'been', 'the', 'a', 'an', 'and', 'or', 'of', 'to', 'in',
        'on', 'for', 'with', 'by', 'from', 'at', 'as', 'it', 'its', 'this', 'that', 'these', 'those', 'do', 'does', 'did', 'can', 'could', 'should', 'would',
        'will', 'shall', 'may', 'must', 'i', 'we', 'you', 'they', 'he', 'she', 'my', 'our', 'your', 'their', 'please', 'tell', 'me', 'about', 'explain',
        'describe', 'there', 'any', 'all', 'each', 'every', 'not', 'no', 'if', 'then', 'than', 'so', 'into', 'system', 'module',
    ];

    private const EN_MARKERS = ['what', 'how', 'who', 'which', 'when', 'where', 'why', 'is', 'are', 'the', 'does', 'do', 'can', 'should', 'of', 'and', 'for', 'with', 'please', 'explain'];

    private const MS_MARKERS = ['apa', 'apakah', 'bagaimana', 'siapa', 'bila', 'mana', 'yang', 'dan', 'untuk', 'bagi', 'dengan', 'adakah', 'ialah', 'boleh', 'perlu', 'saya', 'di', 'ke', 'dalam'];

    /** @return list<string> lower-case word tokens (letters/digits, incl. codes like srs-kba-05) */
    public static function tokens(string $text): array
    {
        $text = mb_strtolower($text);
        preg_match_all('/[\p{L}\p{N}]+(?:[-_.][\p{L}\p{N}]+)*/u', $text, $m);

        return array_values(array_filter($m[0], fn (string $t) => mb_strlen($t) >= 2));
    }

    /** @return list<string> distinct content words of a question (stop words removed) */
    public static function contentTokens(string $text): array
    {
        $stop = array_flip(self::STOP);
        $out = [];
        foreach (self::tokens($text) as $t) {
            if (! isset($stop[$t]) && ! in_array($t, $out, true)) {
                $out[] = $t;
            }
        }

        return $out;
    }

    /** "ms" or "en" — simple marker vote; Malay wins ties (APP_LOCALE=ms). */
    public static function language(string $text): string
    {
        $tokens = self::tokens($text);
        $en = count(array_intersect($tokens, self::EN_MARKERS));
        $ms = count(array_intersect($tokens, self::MS_MARKERS));

        return $en > $ms ? 'en' : 'ms';
    }

    /** Lower-case, punctuation-free, single-spaced — groups the same question asked differently cased/punctuated. */
    public static function normalise(string $question): string
    {
        return mb_substr(implode(' ', self::tokens($question)), 0, 500);
    }

    /**
     * Does $haystack contain $token as a word or word prefix/stem? Malay
     * affixes (peng-, -an, -kan) make exact matching too strict, so a token of
     * 5+ letters also matches on its first 5 letters.
     */
    public static function mentions(string $haystackLower, string $token): bool
    {
        if (str_contains($haystackLower, $token)) {
            return true;
        }
        if (mb_strlen($token) >= 6) {
            return str_contains($haystackLower, mb_substr($token, 0, 5));
        }

        return false;
    }
}
