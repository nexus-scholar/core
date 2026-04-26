<?php

declare(strict_types=1);

namespace Nexus\Deduplication\Infrastructure;

/**
 * Unicode-safe title normalizer for deduplication.
 *
 * Never uses strlen() or levenshtein() on raw UTF-8 — see known bug #12.
 * Uses mb_strtolower, iconv transliteration, and mb_str_split for Levenshtein.
 */
final class TitleNormalizer
{
    public function normalize(string $title): string
    {
        // 1. Lowercase (UTF-8 aware)
        $s = mb_strtolower($title, 'UTF-8');

        // 2. Strip HTML entities (e.g. &amp; &lt; &#8211;)
        $s = html_entity_decode($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $s = preg_replace('/&[#a-z0-9]+;/i', ' ', $s) ?? $s;

        // 3. Transliterate diacritics → ASCII
        $transliterated = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
        $s = ($transliterated !== false && $transliterated !== '') ? $transliterated : $s;

        // 4. Strip non-alphanumeric except spaces
        $s = preg_replace('/[^a-z0-9 ]/', '', $s) ?? $s;

        // 5. Collapse multiple spaces
        $s = preg_replace('/\s+/', ' ', $s) ?? $s;

        // 6. Trim
        return trim($s);
    }

    /**
     * Compute similarity ratio (0–100) between two titles.
     * Unicode-safe: uses mb_str_split() to get character arrays,
     * then runs a standard Levenshtein DP on those arrays.
     *
     * NEVER uses strlen() — always mb_strlen() on UTF-8 content (known bug #12).
     */
    public function fuzzyRatio(string $a, string $b): int
    {
        $a = $this->normalize($a);
        $b = $this->normalize($b);

        if ($a === $b) {
            return 100;
        }

        if ($a === '' || $b === '') {
            return 0;
        }

        $aChars = mb_str_split($a);
        $bChars = mb_str_split($b);
        $lenA   = count($aChars);
        $lenB   = count($bChars);

        $dist = $this->levenshteinChars($aChars, $bChars, $lenA, $lenB);

        return (int) round((1 - $dist / max($lenA, $lenB)) * 100);
    }

    /**
     * Standard DP Levenshtein on pre-split character arrays.
     * O(lenA * lenB) time, O(lenB) space (rolling array).
     *
     * @param string[] $a
     * @param string[] $b
     */
    private function levenshteinChars(array $a, array $b, int $lenA, int $lenB): int
    {
        // Use a single rolling row of size lenB+1
        $prev = range(0, $lenB);

        for ($i = 1; $i <= $lenA; $i++) {
            $curr    = [];
            $curr[0] = $i;

            for ($j = 1; $j <= $lenB; $j++) {
                $cost    = ($a[$i - 1] === $b[$j - 1]) ? 0 : 1;
                $curr[$j] = min(
                    $prev[$j] + 1,       // deletion
                    $curr[$j - 1] + 1,   // insertion
                    $prev[$j - 1] + $cost // substitution
                );
            }

            $prev = $curr;
        }

        return $prev[$lenB];
    }
}
