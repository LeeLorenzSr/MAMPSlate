<?php
declare(strict_types=1);

/**
 * URL slug helpers.
 */
final class Slug
{
    /**
     * Convert arbitrary text to a URL-safe slug (lowercase, dash-separated,
     * accents transliterated to ASCII).
     */
    public static function slugify(string $text): string
    {
        $text = trim($text);
        if ($text === '') {
            return '';
        }

        if (function_exists('transliterator_transliterate')) {
            $transliterated = transliterator_transliterate('Any-Latin; Latin-ASCII; Lower()', $text);
            if ($transliterated !== false) {
                $text = $transliterated;
            } else {
                $text = strtolower($text);
            }
        } else {
            $text = strtolower($text);
        }

        $text = preg_replace('/[^a-z0-9]+/', '-', $text);
        $text = trim($text, '-');

        return $text !== '' ? $text : 'article';
    }

    /**
     * Ensure a slug is unique. If it already exists, append -2, -3, ...
     *
     * @param callable(string):bool $exists returns true if the slug is taken.
     */
    public static function ensureUnique(callable $exists, string $slug, ?int $excludeId = null): string
    {
        $base = $slug !== '' ? $slug : 'article';
        $candidate = $base;
        $n = 1;

        while ($exists($candidate, $excludeId)) {
            $n++;
            $candidate = $base . '-' . $n;
        }

        return $candidate;
    }
}
